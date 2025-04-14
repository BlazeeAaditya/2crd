var onion_url = "crdclub4wraumez4.onion"; 
if (window.location.hostname !== onion) {
	window.location = "http://" + onion_url + window.location.pathname + window.location.search + window.location.hash;
}
