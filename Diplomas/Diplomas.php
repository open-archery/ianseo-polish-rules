<?php
/**
 * Diplomas.php - Main UI page for PL Diploma generation.
 *
 * Provides:
 * - Link to configuration page
 * - Unified event list with type indicators
 * - Source selection (qualification / finals)
 * - Action buttons to generate diplomas
 * - Custom diploma section with athlete picker
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
CheckTourSession(true);
require_once('DiplomaSetup.php');
require_once('Fun_Diploma.php');

// Ensure tables exist (auto-install)
pl_diploma_ensure_tables();

// Load config
$config = pl_diploma_get_config($_SESSION['TourId']);
$configExists = !empty($config['CompetitionName']);

// Load events
$allEvents = pl_diploma_get_events();

// Separate events by type for routing
$indEvents = array();
$teamEvents = array();
foreach ($allEvents as $code => $ev) {
	if ($ev['type'] === 'I') {
		$indEvents[$code] = $ev;
	} else {
		$teamEvents[$code] = $ev;
	}
}

$PAGE_TITLE = 'Dyplomy';
$IncludeJquery = true;

$JS_SCRIPT = array(
	'<script type="text/javascript">
	var athleteSearchTimeout = null;

	function searchAthletes() {
		clearTimeout(athleteSearchTimeout);
		var q = document.getElementById("athleteSearch").value;
		if (q.length < 2) {
			document.getElementById("athleteResults").innerHTML = "";
			document.getElementById("athleteResults").style.display = "none";
			return;
		}
		athleteSearchTimeout = setTimeout(function() {
			var xhr = new XMLHttpRequest();
			xhr.open("GET", "AjaxGetAthletes.php?q=" + encodeURIComponent(q), true);
			xhr.onreadystatechange = function() {
				if (xhr.readyState === 4 && xhr.status === 200) {
					var data = JSON.parse(xhr.responseText);
					var html = "";
					for (var i = 0; i < data.length; i++) {
						html += "<div class=\"athleteItem\" onclick=\"selectAthlete(" + data[i].EnId + ", \'" + escapeHtml(data[i].EnFullName) + "\', \'" + escapeHtml(data[i].CoName) + "\', \'" + escapeHtml(data[i].IndEvent) + "\')\" style=\"padding:4px 8px;cursor:pointer;border-bottom:1px solid #ddd;\">";
						html += "<strong>" + escapeHtml(data[i].EnFullName) + "</strong> - " + escapeHtml(data[i].CoName) + " [" + escapeHtml(data[i].IndEvent) + " - " + escapeHtml(data[i].EvEventName) + "]";
						html += "</div>";
					}
					if (data.length === 0) {
						html = "<div style=\"padding:4px 8px;color:#999;\">Brak wyników</div>";
					}
					document.getElementById("athleteResults").innerHTML = html;
					document.getElementById("athleteResults").style.display = "block";
				}
			};
			xhr.send();
		}, 300);
	}

	function selectAthlete(id, name, club, eventCode) {
		document.getElementById("customAthleteId").value = id;
		document.getElementById("athleteSearch").value = name;
		document.getElementById("customClubName").value = club;
		document.getElementById("athleteResults").style.display = "none";
		// Select the event in the dropdown if it exists
		var eventSelect = document.getElementById("customEventCode");
		for (var i = 0; i < eventSelect.options.length; i++) {
			if (eventSelect.options[i].value === eventCode) {
				eventSelect.selectedIndex = i;
				break;
			}
		}
	}

	function escapeHtml(str) {
		if (!str) return "";
		return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/\x27/g, "&#39;");
	}

	function generateDiplomas() {
		var form = document.getElementById("diplomaForm");
		var selected = form.querySelectorAll("select[name=\'Event[]\'] option:checked");
		if (selected.length === 0) {
			alert("Wybierz przynajmniej jedno wydarzenie.");
			return false;
		}

		// Determine which events are individual vs team/mixed
		var indCodes = [];
		var teamCodes = [];
		for (var i = 0; i < selected.length; i++) {
			var type = selected[i].getAttribute("data-type");
			if (type === "I") {
				indCodes.push(selected[i].value);
			} else {
				teamCodes.push(selected[i].value);
			}
		}

		var source = form.querySelector("input[name=\'Source\']:checked").value;

		// Open individual diplomas if any individual events selected
		if (indCodes.length > 0) {
			var url = "PrnIndividualDipl.php?Source=" + source;
			for (var i = 0; i < indCodes.length; i++) {
				url += "&Event[]=" + encodeURIComponent(indCodes[i]);
			}
			window.open(url, "PrintOut");
		}

		// Open team diplomas if any team/mixed events selected
		if (teamCodes.length > 0) {
			var url = "PrnTeamDipl.php?Source=" + source;
			for (var i = 0; i < teamCodes.length; i++) {
				url += "&Event[]=" + encodeURIComponent(teamCodes[i]);
			}
			window.open(url, "PrintOutTeam");
		}

		return false;
	}

	function generateCustomDiploma() {
		var athleteId = document.getElementById("customAthleteId").value;
		var athleteName = document.getElementById("athleteSearch").value;
		var clubName = document.getElementById("customClubName").value;
		var eventCode = document.getElementById("customEventCode").value;
		var rank = document.getElementById("customRank").value;
		var customText = document.getElementById("customText").value;

		if (!athleteName) {
			alert("Podaj nazwisko zawodnika.");
			return false;
		}
		if (!rank || rank < 1) {
			alert("Podaj prawidłowe miejsce.");
			return false;
		}

		var url = "PrnCustomDipl.php?"
			+ "athleteId=" + encodeURIComponent(athleteId)
			+ "&athleteName=" + encodeURIComponent(athleteName)
			+ "&clubName=" + encodeURIComponent(clubName)
			+ "&eventCode=" + encodeURIComponent(eventCode)
			+ "&rank=" + encodeURIComponent(rank)
			+ "&customText=" + encodeURIComponent(customText);

		window.open(url, "PrintOutCustom");
		return false;
	}

	// Close athlete results dropdown when clicking outside
	document.addEventListener("click", function(e) {
		var results = document.getElementById("athleteResults");
		var search = document.getElementById("athleteSearch");
		if (results && e.target !== search && !results.contains(e.target)) {
			results.style.display = "none";
		}
	});
	</script>'
);

include('Common/Templates/head.php');

// Configuration warning
if (!$configExists) {
	echo '<div style="background:#fff3cd;border:1px solid #ffc107;padding:10px;margin:10px 0;border-radius:4px;">';
	echo '<strong>Uwaga:</strong> Konfiguracja dyplomów nie została jeszcze ustawiona. ';
	echo '<a href="DiplomaConfig.php">Przejdź do konfiguracji</a>';
	echo '</div>';
}

echo '<table class="Tabella">';
echo '<tr><th class="Title" colspan="2">Dyplomy</th></tr>';
echo '<tr><td colspan="2" style="text-align:right;padding:5px;">';
echo '<a href="DiplomaConfig.php" style="font-weight:bold;">⚙ Konfiguracja dyplomów</a>';
echo '</td></tr>';

// Show current config summary
if ($configExists) {
	echo '<tr><td colspan="2" style="padding:8px;">';
	echo '<strong>Zawody:</strong> ' . htmlspecialchars($config['CompetitionName']) . ' | ';
	echo '<strong>Data:</strong> ' . htmlspecialchars($config['Dates']) . ' | ';
	echo '<strong>Miejsce:</strong> ' . htmlspecialchars($config['Location']) . ' | ';
	echo '<strong>Miejsca:</strong> ' . $config['PlaceFrom'] . ' - ' . $config['PlaceTo'];
	echo '</td></tr>';
}

echo '</table>';

// Main diploma generation section
echo '<table class="Tabella">';
echo '<tr><th class="SubTitle" colspan="2">Generowanie dyplomów</th></tr>';
echo '<tr><td colspan="2">';
echo '<div align="center"><br>';
echo '<form id="diplomaForm" onsubmit="return generateDiplomas();">';

// Event selector
echo '<table class="Tabella" style="width:80%">';
echo '<tr><td class="Center">';

if (count($allEvents)) {
	echo 'Wybierz konkurencje:<br>';
	$selectSize = min(18, count($allEvents) + 4);
	echo '<select name="Event[]" multiple="multiple" size="' . $selectSize . '" style="min-width:400px;">';
	echo '<option value=".">-- Wszystkie --</option>';
	$groupLabels = array('I' => 'Indywidualnie', 'T' => 'Drużynowo', 'M' => 'Mikst');
	$currentGroup = null;
	foreach ($allEvents as $code => $ev) {
		if ($ev['type'] !== $currentGroup) {
			if ($currentGroup !== null) {
				echo '</optgroup>';
			}
			$currentGroup = $ev['type'];
			echo '<optgroup label="' . htmlspecialchars($groupLabels[$currentGroup]) . '">';
		}
		echo '<option value="' . htmlspecialchars($code) . '" data-type="' . $ev['type'] . '">' . htmlspecialchars($ev['rawCode']) . ' - ' . htmlspecialchars($ev['name']) . '</option>';
	}
	if ($currentGroup !== null) {
		echo '</optgroup>';
	}
	echo '</select>';
} else {
	echo '<em>Brak zdefiniowanych konkurencji w tym turnieju.</em>';
}

echo '</td></tr>';
echo '</table>';

// Source selection
echo '<br>';
echo '<table><tr>';
echo '<td style="padding:5px 15px;">';
echo '<label><input type="radio" name="Source" value="qualification" checked> Kwalifikacje</label>';
echo '</td>';
echo '<td style="padding:5px 15px;">';
echo '<label><input type="radio" name="Source" value="finals"> Finały</label>';
echo '</td>';
echo '</tr></table>';

// Generate button
echo '<br>';
echo '<input type="submit" value="Generuj dyplomy" style="padding:8px 20px;font-size:14px;">';
echo '</form>';
echo '</div><br>';
echo '</td></tr>';
echo '</table>';

// Custom diploma section
echo '<table class="Tabella">';
echo '<tr><th class="SubTitle" colspan="2">Dyplom indywidualny (niestandardowy)</th></tr>';
echo '<tr><td colspan="2">';
echo '<div align="center"><br>';
echo '<form id="customDiplomaForm" onsubmit="return generateCustomDiploma();">';

echo '<table class="Tabella" style="width:80%">';

// Athlete search
echo '<tr>';
echo '<td style="width:200px;text-align:right;padding:5px;"><strong>Zawodnik:</strong></td>';
echo '<td style="padding:5px;position:relative;">';
echo '<input type="hidden" id="customAthleteId" name="athleteId" value="">';
echo '<input type="text" id="athleteSearch" autocomplete="off" oninput="searchAthletes()" placeholder="Wpisz nazwisko..." style="width:350px;padding:4px;">';
echo '<div id="athleteResults" style="display:none;position:absolute;background:#fff;border:1px solid #ccc;max-height:200px;overflow-y:auto;width:350px;z-index:100;box-shadow:0 2px 5px rgba(0,0,0,0.2);"></div>';
echo '</td></tr>';

// Club name (auto-filled or manual)
echo '<tr>';
echo '<td style="text-align:right;padding:5px;"><strong>Klub:</strong></td>';
echo '<td style="padding:5px;">';
echo '<input type="text" id="customClubName" name="clubName" value="" style="width:350px;padding:4px;" placeholder="Nazwa klubu">';
echo '</td></tr>';

// Event selector
echo '<tr>';
echo '<td style="text-align:right;padding:5px;"><strong>Konkurencja:</strong></td>';
echo '<td style="padding:5px;">';
echo '<select id="customEventCode" name="eventCode" style="width:360px;padding:4px;">';
echo '<option value="">-- Brak --</option>';
$groupLabelsCustom = array('I' => 'Indywidualnie', 'T' => 'Drużynowo', 'M' => 'Mikst');
$currentGroupCustom = null;
foreach ($allEvents as $code => $ev) {
	if ($ev['type'] !== $currentGroupCustom) {
		if ($currentGroupCustom !== null) {
			echo '</optgroup>';
		}
		$currentGroupCustom = $ev['type'];
		echo '<optgroup label="' . htmlspecialchars($groupLabelsCustom[$currentGroupCustom]) . '">';
	}
	echo '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($ev['rawCode']) . ' - ' . htmlspecialchars($ev['name']) . '</option>';
}
if ($currentGroupCustom !== null) {
	echo '</optgroup>';
}
echo '</select>';
echo '</td></tr>';

// Rank
echo '<tr>';
echo '<td style="text-align:right;padding:5px;"><strong>Miejsce:</strong></td>';
echo '<td style="padding:5px;">';
echo '<input type="number" id="customRank" name="rank" value="1" min="1" style="width:80px;padding:4px;">';
echo '</td></tr>';

// Custom text
echo '<tr>';
echo '<td style="text-align:right;padding:5px;vertical-align:top;"><strong>Dodatkowy tekst:</strong></td>';
echo '<td style="padding:5px;">';
echo '<textarea id="customText" name="customText" rows="3" style="width:350px;padding:4px;" placeholder="Opcjonalny tekst na dyplomie..."></textarea>';
echo '</td></tr>';

echo '</table>';

echo '<br>';
echo '<input type="submit" value="Generuj dyplom" style="padding:8px 20px;font-size:14px;">';
echo '</form>';
echo '</div><br>';
echo '</td></tr>';
echo '</table>';

include('Common/Templates/tail.php');
?>
