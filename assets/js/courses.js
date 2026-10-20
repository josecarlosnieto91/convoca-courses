/**
 * Convoca Courses — mejora progresiva.
 *
 * Sin este fichero todo funciona: los formularios se envían por admin-post.php y la página recarga.
 * Con él, la marca de lección completada se hace por AJAX y la URL firmada del vídeo se renueva sola.
 */
(function () {
	'use strict';

	var settings = window.convocaCourses || {};

	function request(action, data) {
		var body = new URLSearchParams(data);
		body.set('action', action);
		body.set('nonce', settings.nonce || '');

		return fetch(settings.ajaxUrl, {
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			method: 'POST',
			body: body.toString()
		}).then(function (response) {
			if (!response.ok) {
				throw new Error('HTTP ' + response.status);
			}
			return response.json();
		});
	}

	function refreshVideo(video) {
		var lessonId = video.getAttribute('data-lesson');
		if (!lessonId) {
			return;
		}

		request(settings.action, { lesson_id: lessonId })
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data || !payload.data.src) {
					return;
				}
				var source = video.querySelector('source');
				if (source) {
					source.setAttribute('src', payload.data.src);
					video.load();
				}
			})
			.catch(function () {
				/* Si falla, se queda la URL que ya tenía. */
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var videos = document.querySelectorAll('.convoca-courses__video video');
		Array.prototype.forEach.call(videos, function (video) {
			video.addEventListener('error', function () {
				refreshVideo(video);
			}, { once: true });
		});
	});
}());
