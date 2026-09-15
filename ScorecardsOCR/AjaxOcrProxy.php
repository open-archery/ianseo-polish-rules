<?php
/**
 * AjaxOcrProxy.php — Server-side proxy for OpenAI vision API scorecard extraction.
 *
 * Receives a base64-encoded image from the browser, forwards it to the OpenAI
 * chat completions endpoint using the API key stored in PLOcrConfig, and returns
 * the structured JSON response to the browser.
 *
 * The API key never travels to the browser — it is read server-side only.
 *
 * POST params:
 *   image_b64  string  Base64-encoded JPEG (already resized client-side, no data: prefix)
 *   label      string  Optional quadrant label ("Top-left" etc.) for logging
 */

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php';
CheckTourSession(false);
require_once __DIR__ . '/Fun_ScorecardsOcr.php';

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ───────────────────────────────────────────────────────────────────

function ocr_error(string $message, int $httpCode = 400): never
{
    http_response_code($httpCode);
    echo json_encode(['error' => $message]);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────────────

$imageb64 = trim($_POST['image_b64'] ?? '');
if ($imageb64 === '') {
    ocr_error('Brak obrazu (image_b64).');
}

pl_ocr_install();

$apiKey = pl_ocr_get_config('api_key');
if ($apiKey === '') {
    ocr_error('Klucz API nie jest skonfigurowany. Przejdź do Konfiguracja OCR i dodaj klucz.', 503);
}

$model = pl_ocr_get_config('model', 'gpt-4.1-mini');

// ── System prompt ─────────────────────────────────────────────────────────────

$systemPrompt = <<<'PROMPT'
You are an OCR extraction engine for Polish archery scorecards (PZŁucz / FITA format).

# TASK
First fill in the "reasoning" field with a step-by-step visual walkthrough:
- Describe the physical layout you see (number of ends, number of rows per end, column headers)
- Read the printed barcode text below the barcode (format: {number}-{DIV}-{CLASS}-{session})
- For EACH end, read each cell left-to-right and describe what digit/letter you see
- Only then fill in the scorecard data

This reasoning forces you to look carefully before writing values. Do NOT skip it.

Extract ONLY raw data from the image.

# RULES
- Do NOT calculate totals.
- Do NOT validate.
- Do NOT infer missing values.
- Do NOT compute running sums.
- Do NOT check correctness.

# OUTPUT REQUIREMENTS
Return JSON only in this structure:

{
  "reasoning": "...",
  "barcode_text": "5083-R-U21M-2",
  "target_label": "1C",
  "session_label": "70m-2",
  "scorecards": [
    {
      "archer_name": "string or null",
      "round_type": "string or null",
      "ends": [
        {
          "end_number": 1,
          "sub_row_a": {
            "arrows": ["X", 10, 9],
            "recorded_suma": 29,
            "recorded_10x": 2,
            "recorded_x": 1
          },
          "sub_row_b": {
            "arrows": [8, 7, "M"],
            "recorded_suma": 15,
            "recorded_10x": null,
            "recorded_x": null
          },
          "recorded_razem": 44,
          "recorded_running": 44
        }
      ],
      "recorded_grand_total": 123,
      "recorded_grand_10x": 5,
      "recorded_grand_x": 3,
      "notes": null
    }
  ]
}

# BARCODE TEXT — CRITICAL
- Look for a printed barcode near the bottom of the scorecard quadrant
- Below the barcode there is a printed text in the format: {number}-{DIV}-{CLASS}-{session}
  Examples: "5083-R-U21M-2", "6005-R-U21M-2", "5698-R-U21M-2"
- Extract this EXACTLY as printed into the "barcode_text" field
- If you cannot read the barcode text, set "barcode_text" to null

# TARGET LABEL
- The target label is printed in a box in the top-right area of the scorecard
  Examples: "1A", "1B", "1C", "1D"
- Extract into "target_label"; null if not visible

# SESSION LABEL
- The session label is printed in the top-left column header area
  Examples: "70m-2", "18m-1"
- Extract into "session_label"; null if not visible

# COLUMN LAYOUT
The physical scorecard columns per sub-row, from left to right:
  1 | 2 | 3 | Suma | [Razem] | [Running] | [10+X] | [X]
- 1 - Score for the first arrow (0-10 or "X" or "M")
- 2 - Score for the second arrow (0-10 or "X" or "M")
- 3 - Score for the third arrow (0-10 or "X" or "M")
- Suma = participant recorded sum of the 3 arrows on THIS sub-row
- Razem = participant recorded sum of ALL 6 arrows of the end (written only on the B sub-row)
- Running = participant recorded cumulative running total (written only on the B sub-row)
- 10+X = participant recorded count of arrows hitting the 10-ring or X-ring in THIS sub-row
- X = participant recorded count of arrows hitting the X-ring in THIS sub-row

All values are participant recorded — extract what is explicitly written, without inference.

# READING 10+X AND X CELLS — CRITICAL RULES
1. Only write a number if you can see a handwritten digit physically written inside that specific cell. No digit visible = null.
2. DO NOT calculate or infer these values from the arrows.
3. NEVER read digits from nearby Razem or Running values into these fields; 10+X and X hold only 0–3.
4. When in doubt → null.
- recorded_grand_10x and recorded_grand_x are the TOTAL counts written in the bottom "Razem" summary row.
PROMPT;

// ── JSON schema for structured output ─────────────────────────────────────────

$arrowItem = [
    'anyOf' => [
        ['type' => 'integer'],
        ['type' => 'string', 'enum' => ['X', 'M']],
    ],
];
$nullableInt = ['anyOf' => [['type' => 'integer'], ['type' => 'null']]];
$nullableStr = ['anyOf' => [['type' => 'string'], ['type' => 'null']]];

$subRowSchema = [
    'type'                 => 'object',
    'additionalProperties' => false,
    'properties'           => [
        'arrows'        => ['type' => 'array', 'items' => $arrowItem],
        'recorded_suma' => $nullableInt,
        'recorded_10x'  => ['anyOf' => [['type' => 'integer', 'minimum' => 0, 'maximum' => 3], ['type' => 'null']]],
        'recorded_x'    => ['anyOf' => [['type' => 'integer', 'minimum' => 0, 'maximum' => 3], ['type' => 'null']]],
    ],
    'required' => ['arrows', 'recorded_suma'],
];

$endSchema = [
    'type'                 => 'object',
    'additionalProperties' => false,
    'properties'           => [
        'end_number'      => ['type' => 'integer'],
        'sub_row_a'       => $subRowSchema,
        'sub_row_b'       => $subRowSchema,
        'recorded_razem'  => $nullableInt,
        'recorded_running' => $nullableInt,
    ],
    'required' => ['end_number', 'sub_row_a', 'sub_row_b', 'recorded_razem', 'recorded_running'],
];

$scorecardSchema = [
    'type'                 => 'object',
    'additionalProperties' => false,
    'properties'           => [
        'archer_name'          => $nullableStr,
        'round_type'           => $nullableStr,
        'ends'                 => ['type' => 'array', 'items' => $endSchema],
        'recorded_grand_total' => $nullableInt,
        'recorded_grand_10x'   => $nullableInt,
        'recorded_grand_x'     => $nullableInt,
        'notes'                => $nullableStr,
    ],
    'required' => ['archer_name', 'round_type', 'ends', 'recorded_grand_total', 'recorded_grand_10x', 'recorded_grand_x', 'notes'],
];

$responseSchema = [
    'type'                 => 'object',
    'additionalProperties' => false,
    'properties'           => [
        'reasoning'     => ['type' => 'string'],
        'barcode_text'  => $nullableStr,
        'target_label'  => $nullableStr,
        'session_label' => $nullableStr,
        'scorecards'    => ['type' => 'array', 'items' => $scorecardSchema],
    ],
    'required' => ['reasoning', 'barcode_text', 'target_label', 'session_label', 'scorecards'],
];

// ── Build OpenAI request ──────────────────────────────────────────────────────

$payload = [
    'model'           => $model,
    'response_format' => [
        'type'        => 'json_schema',
        'json_schema' => [
            'name'   => 'archery_scorecard_pl',
            'strict' => false,
            'schema' => $responseSchema,
        ],
    ],
    'messages' => [
        [
            'role'    => 'system',
            'content' => $systemPrompt,
        ],
        [
            'role'    => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Extract this archery scorecard. Return JSON only.',
                ],
                [
                    'type'      => 'image_url',
                    'image_url' => [
                        'url'    => 'data:image/jpeg;base64,' . $imageb64,
                        'detail' => 'high',
                    ],
                ],
            ],
        ],
    ],
];

// ── Call OpenAI via cURL ──────────────────────────────────────────────────────

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$body     = curl_exec($ch);
$errno    = curl_errno($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno !== CURLE_OK || $body === false) {
    ocr_error('Błąd sieci: nie można połączyć się z OpenAI.', 503);
}

if ($httpCode !== 200) {
    $errMsg = '';
    $decoded = json_decode($body, true);
    if (isset($decoded['error']['message'])) {
        $errMsg = $decoded['error']['message'];
    }
    switch ($httpCode) {
        case 401: ocr_error('Nieprawidłowy klucz API (401). Zaktualizuj go w Konfiguracja OCR.', 401);
        case 429: ocr_error('Przekroczony limit zapytań API (429). Odczekaj chwilę.', 429);
        case 500: ocr_error('Błąd serwera OpenAI (500). Spróbuj ponownie.', 502);
        default:  ocr_error("Błąd API {$httpCode}" . ($errMsg ? ": {$errMsg}" : ''), 502);
    }
}

// Return the raw OpenAI response body directly to the browser
echo $body;
