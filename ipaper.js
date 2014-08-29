function iPaper(docId, access_key, height, width, mode, page, htmlElementId)
{
	var ipaper = scribd.Document.getDoc(docId, access_key);
	ipaper.addParam('height', height);
	ipaper.addParam('width', width);
	ipaper.addParam('mode', mode);
	ipaper.addParam('page', page);
	ipaper.write(htmlElementId);
}