var docChange = false;
function confirmClose(url,tekst)
{
  if (docChange) {
 		if(confirm(tekst)){
 			document.location = url;
		}
	} else document.location = url;
}
