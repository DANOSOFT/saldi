<?php

$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 8l-4 4 4 4M16 12H9"></path></svg>';
$icon_kassekladde = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm40-80h200v-80H240v80Zm0-160h480v-80H240v80Zm0-160h480v-80H240v80ZM200-200v-560 560Z"/></svg>';
$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0-83-31.5-156T763-197q-54-54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';
$add_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160Zm40 200q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54-54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

$tekst = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
$title_display = findtekst('1072|Kassekladde', $sprog_id) . ($kladde_id ? " $kladde_id" : "");
if ($popup || $visipop) {
	$backUrlKK = "../includes/luk.php?tabel=kladdeliste&amp;id=$kladde_id&exitDraft=$kladde_id";
} else {
	$backUrlKK = $backUrl;
}

print "<tr><td height='25' align='center' valign='top' style='position: sticky; top: 0; z-index: 100; background: white;'>";
print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody><tr>"; # Tabel 1.1 ->

print "<td width='5%' style='$buttonStyle'>
	<a href=\"javascript:confirmClose('$backUrlKK','$tekst')\" accesskey='L'>
	<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
	$icon_back " . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";

print "<td width='85%' style='$topStyle' align='left'><table border='0' cellspacing='2' cellpadding='0'><tbody><tr>\n"; # Tabel 1.1.1 ->

print "<td width='200px' align='center' id='kassekladde-btn'>
	<button class='headerbtn navbtn-top' style='$butDownStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">
	$icon_kassekladde $title_display 
	</button></td>";


print "</tr></tbody></table></td>\n"; # <- Tabel 1.1.1




print "<td id='tutorial-help' width='5%' style='$buttonStyle'>";
print "<a href='https://saldi.dk/dok/ledgerGuide.pdf' target='_blank'>";
print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
print $help_icon;
print findtekst('2564|Hjælp', $sprog_id) . "</button></a></td>";

if ($ny) {
	print "<td width='5%' style='$buttonStyle'>";
	print "<a href=\"javascript:confirmClose('../finans/kassekladde.php?exitDraft=$kladde_id','$tekst')\" accesskey='N'>";
	print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print $add_icon . " " . $ny;
	print "</button></a></td>";
}

print "</tr></tbody></table></td></tr>\n"; # <- Tabel 1.1

?>

<style>
	/* ===== KASSEKLADDE STYLES (Menu S) - Simple version ===== */
	
	html, body {
		margin: 0;
		padding: 0;
	}
	
	body {
		padding-bottom: 70px;
	}
	
	/* Button styles */
	.headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;
	}
	
	/* Basic table styling */
	.outerTable {
		width: 100%;
		border-collapse: collapse;
	}
	
	/* Note row */
	.kassekladde-note-tb {
		/* background: white !important; */
	}
	
	.kassekladde-note-tb td {
		/* background: white !important; */
		padding: 4px 8px !important;
	}
	
	/* Table headers */
	.kassekladde-thead {
		/* background: #f1f1f1 !important; */
	}
	
	.kassekladde-thead tr {
		/* background: #f1f1f1 !important; */
	}
	
	.kassekladde-thead th,
	.kassekladde-thead td {
		/* background: #f1f1f1 !important; */
		padding: 5px 8px !important;
		white-space: nowrap;
		font-weight: bold;
	}
	
	/* Table body */
	#kassekladde-tbody tr {
		background: white;
	}
	
	#kassekladde-tbody tr:hover {
		/* background: #f5f5f5; */
	}
	
	/* Footer styles */
	#kassekladde-sticky-footer {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		z-index: 1000;
		background: #f5f5f5;
		border-top: 1px solid #ddd;
		padding: 8px 15px;
		box-shadow: 0 -2px 4px rgba(0,0,0,0.1);
		display: flex;
		justify-content: center;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
	}
	
	.fixedFooter {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		border-top: 1px solid #ccc;
		display: flex;
		justify-content: space-between;
		align-items: center;
		width: 100%;
		z-index: 1000;
		background-color: #f0f0f0;
		padding: 10px;
		font-weight: bold;
	}

	.fixedFooter select, 
	.fixedFooter button {
		padding: 5px 10px;
		font-size: 14px;
	}

	.fixedFooter .left, 
	.fixedFooter .right {
		display: flex;
		align-items: center;
		padding-right: 25px;
	}

	.fixedFooter .left a, 
	.fixedFooter .left span.disabled {
		margin-right: 10px;
		text-decoration: none;
		color: #0a0a0a;
	}

	.fixedFooter .left span.disabled {
		color: #6e6565;
	}

	.fixedFooter .center {
		justify-content: center;
		text-align: center;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.fixedFooter form {
		margin: 0;
	}
	
	#kopierButtonRowFooter {
		display: flex;
		justify-content: center;
		align-items: center;
		margin-bottom: 10px;
		height: 50px;
		width: 35%;
	}
	
	/* ===== STICKY HEADER OVERLAY - Only visible when scrolling long tables ===== */
	#kk-sticky-header {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 9998;
		background: white;
		box-shadow: 0 2px 8px rgba(0,0,0,0.15);
		opacity: 0;
		pointer-events: none;
		will-change: opacity;
		transition: opacity 0.1s ease-out;
	}
	
	#kk-sticky-header.visible {
		opacity: 1;
		pointer-events: auto;
	}
	
	#kk-sticky-header .kk-sh-topbar {
		background: white;
	}
	
	#kk-sticky-header .kk-sh-topbar table {
		width: 100%;
	}
	
	#kk-sticky-header .kk-sh-note {
		background: white;
		padding: 2px 0;
		border-bottom: 1px solid #eee;
	}
	
	#kk-sticky-header .kk-sh-note table {
		width: 100%;
	}
	
	#kk-sticky-header .kk-sh-thead {
		/* background: #f1f1f1; */
	}
	
	#kk-sticky-header .kk-sh-thead table {
		width: 100%;
		/* background: #f1f1f1; */
		border-collapse: collapse;
	}
	
	#kk-sticky-header .kk-sh-thead th,
	#kk-sticky-header .kk-sh-thead td {
		/* background: #f1f1f1 !important; */
		padding: 5px 8px !important;
		font-weight: bold;
		white-space: nowrap;
	}
</style>

<script>
(function() {
	var stickyHeader = null;
	var thead = null;
	var outerTable = null;
	var isVisible = false;
	var rafId = null;
	
	function createStickyHeader() {
		outerTable = document.querySelector('.outerTable');
		thead = document.querySelector('.kassekladde-thead');
		var noteRow = document.querySelector('.kassekladde-note-tb');
		
		if (!outerTable || !thead) return false;
		
		// Check if table has enough rows to need sticky header
		var tbody = document.getElementById('kassekladde-tbody');
		if (tbody && tbody.children.length < 15) return false; // Don't create for short tables
		
		var topHeaderRow = outerTable.querySelector('tbody > tr:first-child');
		if (!topHeaderRow) return false;
		
		// Create sticky header container
		stickyHeader = document.createElement('div');
		stickyHeader.id = 'kk-sticky-header';
		
		// Clone top bar
		var topBarDiv = document.createElement('div');
		topBarDiv.className = 'kk-sh-topbar';
		var topTable = document.createElement('table');
		topTable.innerHTML = '<tbody>' + topHeaderRow.innerHTML + '</tbody>';
		topBarDiv.appendChild(topTable);
		stickyHeader.appendChild(topBarDiv);
		
		// Clone note row if exists
		if (noteRow) {
			var noteTable = noteRow.querySelector('table');
			if (noteTable) {
				var noteDiv = document.createElement('div');
				noteDiv.className = 'kk-sh-note';
				noteDiv.appendChild(noteTable.cloneNode(true));
				stickyHeader.appendChild(noteDiv);
			}
		}
		
		// Clone thead
		var theadRow = thead.querySelector('tr');
		if (theadRow) {
			var theadDiv = document.createElement('div');
			theadDiv.className = 'kk-sh-thead';
			theadDiv.id = 'kk-sh-thead-wrap';
			var theadTable = document.createElement('table');
			theadTable.id = 'kk-sh-thead-table';
			theadTable.innerHTML = '<thead><tr>' + theadRow.innerHTML + '</tr></thead>';
			theadDiv.appendChild(theadTable);
			stickyHeader.appendChild(theadDiv);
		}
		
		document.body.appendChild(stickyHeader);
		return true;
	}
	
	function syncColumnWidths() {
		if (!thead || !stickyHeader) return;
		
		var origCells = thead.querySelectorAll('tr:first-child > *');
		var cloneTable = document.getElementById('kk-sh-thead-table');
		if (!cloneTable) return;
		
		var cloneCells = cloneTable.querySelectorAll('thead tr > *');
		var totalWidth = 0;
		
		for (var i = 0; i < origCells.length && i < cloneCells.length; i++) {
			var w = origCells[i].offsetWidth;
			cloneCells[i].style.width = w + 'px';
			cloneCells[i].style.minWidth = w + 'px';
			totalWidth += w;
		}
		
		cloneTable.style.width = totalWidth + 'px';
	}
	
	function handleScroll() {
		if (rafId) return;
		rafId = requestAnimationFrame(function() {
			rafId = null;
			if (!thead || !stickyHeader || !outerTable) return;
			
			var theadRect = thead.getBoundingClientRect();
			var tableRect = outerTable.getBoundingClientRect();
			
			// Show when thead scrolls out of view AND table is still visible
			var shouldShow = theadRect.bottom < 0 && tableRect.bottom > 100;
			
			if (shouldShow && !isVisible) {
				syncColumnWidths();
				stickyHeader.classList.add('visible');
				isVisible = true;
			} else if (!shouldShow && isVisible) {
				stickyHeader.classList.remove('visible');
				isVisible = false;
			}
		});
	}
	
	function init() {
		if (createStickyHeader()) {
			window.addEventListener('scroll', handleScroll, { passive: true });
			window.addEventListener('resize', function() {
				if (isVisible) syncColumnWidths();
			});
		}
	}
	
	// Initialize after DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() { setTimeout(init, 100); });
	} else {
		setTimeout(init, 100);
	}
})();
</script>
