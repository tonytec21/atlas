var video = document.getElementById('video');

// Get access to the camera!
if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    // Not adding `{ audio: true }` since we only want video
    navigator.mediaDevices.getUserMedia({ video: true }).then(function(stream) {
   //video.src = window.URL.createObjectURL(stream);
        video.srcObject = stream;
        video.play();
    });
}

// Elements for taking the snapshot
var canvas = document.getElementById('canvas');
var context = canvas.getContext('2d');
var video = document.getElementById('video');

// Trigger photo take
document.getElementById("snap").addEventListener("click", function() {
	context.drawImage(video, 0, 0, 640, 480);
});

// Download canvas as image
function download(){
		var download = document.getElementById("download");
		var image = document.getElementById("canvas").toDataURL("image/png").replace("image/png", "image/octet-stream");
		download.setAttribute("href", image);
		//download.setAttribute("download","archive.png");
	}
