/*
	- LiveQualification.js -
	Polls LiveQualificationData.php for the selected session+distance and
	redraws the target/athlete table. Read-only: no write call exists here.
*/

var LiveQual_ReloadTime = 5000; // matches Qualification/Fun_AJAX_CheckTargetUpdate.js's ReloadTime
var LiveQual_Timer = null;
var LiveQual_Seq = 0; // guards against a stale request (previous selection) resolving after a newer one

function LiveQual_Refresh()
{
	var session = document.getElementById('x_Session').value;
	var distance = document.getElementById('x_Distance').value;
	var tbody = document.getElementById('tbodyLiveQual');
	var summary = document.getElementById('idLiveQualSummary');

	if (LiveQual_Timer) {
		clearTimeout(LiveQual_Timer);
		LiveQual_Timer = null;
	}

	var seq = ++LiveQual_Seq;

	if (session == -1 || distance == -1) {
		tbody.innerHTML = '';
		summary.textContent = '';
		return;
	}

	fetch('LiveQualificationData.php?Session=' + encodeURIComponent(session) + '&Distance=' + encodeURIComponent(distance))
		.then(function (resp) { return resp.json(); })
		.then(function (data) {
			if (seq !== LiveQual_Seq) {
				return; // superseded by a newer selection/refresh — discard this response
			}
			if (data.error) {
				tbody.innerHTML = '';
				summary.textContent = '';
				return;
			}
			summary.textContent = 'Tarcze z zaległościami: ' + data.flaggedTargets + ' / ' + data.totalTargets;
			LiveQual_Render(data.targets);
		})
		.catch(function () {
			if (seq !== LiveQual_Seq) {
				return;
			}
			tbody.innerHTML = '';
			summary.textContent = 'Nie udało się odświeżyć danych.';
		})
		.then(function () {
			if (seq === LiveQual_Seq) {
				LiveQual_Timer = setTimeout(LiveQual_Refresh, LiveQual_ReloadTime);
			}
		});
}

function LiveQual_Render(targets)
{
	var tbody = document.getElementById('tbodyLiveQual');
	tbody.innerHTML = '';

	var targetNos = Object.keys(targets).sort(function (a, b) { return parseInt(a, 10) - parseInt(b, 10); });

	for (var t = 0; t < targetNos.length; ++t) {
		var no = targetNos[t];
		var rows = targets[no];

		var header = document.createElement('tr');
		var headerCell = document.createElement('th');
		headerCell.className = 'SubTitle';
		headerCell.colSpan = 5;
		headerCell.textContent = 'Tarcza ' + no;
		header.appendChild(headerCell);
		tbody.appendChild(header);

		for (var i = 0; i < rows.length; ++i) {
			var row = rows[i];
			var tr = document.createElement('tr');
			// A DNS/DNF/DSQ/DQB row (non-empty status) is never highlighted as
			// behind — its Status column already explains the missing progress.
			// A dataGap row (scored, but no arrow-by-arrow detail available) gets
			// its own amber marker — neither "behind" nor "on pace" applies.
			var cellClass;
			if (row.status) {
				cellClass = 'Center';
			} else if (row.dataGap) {
				cellClass = 'Center TargetNoComplete';
			} else {
				cellClass = row.isBehind ? 'Center TargetKo' : 'Center TargetOk';
			}
			var statusText = row.status || (row.dataGap ? 'Brak danych o strzałach' : '—');

			tr.appendChild(LiveQual_Cell(row.letter, cellClass));
			tr.appendChild(LiveQual_Cell(row.name, cellClass));
			tr.appendChild(LiveQual_Cell(String(row.score), cellClass));
			tr.appendChild(LiveQual_Cell(String(row.arrowsShot), cellClass));
			tr.appendChild(LiveQual_Cell(statusText, cellClass));

			tbody.appendChild(tr);
		}
	}
}

function LiveQual_Cell(text, className)
{
	var td = document.createElement('td');
	td.className = className;
	td.textContent = text;
	return td;
}
