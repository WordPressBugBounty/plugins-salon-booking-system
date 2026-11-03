/* Salon Booking System - weekly schedule widget (no dependencies). */
(function () {
	'use strict';

	var config = window.slnWeeklySchedule || {};

	function tabs(root) {
		return Array.prototype.slice.call(root.querySelectorAll('[role="tab"]'));
	}

	function selectTab(root, tab, focus) {
		tabs(root).forEach(function (item) {
			var selected = item === tab;
			var panel = document.getElementById(item.getAttribute('aria-controls'));
			item.setAttribute('aria-selected', selected ? 'true' : 'false');
			item.setAttribute('tabindex', selected ? '0' : '-1');
			item.classList.toggle('is-active', selected);
			if (panel) {
				panel.classList.toggle('is-active', selected);
			}
		});
		if (focus) {
			tab.focus();
		}
	}

	function onTabKeydown(root, event) {
		var list = tabs(root);
		var index = list.indexOf(event.target);
		var next = null;
		if (index < 0) {
			return;
		}
		switch (event.key) {
			case 'ArrowRight':
			case 'ArrowDown':
				next = list[(index + 1) % list.length];
				break;
			case 'ArrowLeft':
			case 'ArrowUp':
				next = list[(index - 1 + list.length) % list.length];
				break;
			case 'Home':
				next = list[0];
				break;
			case 'End':
				next = list[list.length - 1];
				break;
		}
		if (next) {
			event.preventDefault();
			selectTab(root, next, true);
		}
	}

	function loadWeek(root, week) {
		var args;
		try {
			args = JSON.parse(root.getAttribute('data-sln-ws-args') || '{}');
		} catch (e) {
			args = {};
		}
		args.week = week;

		var url = new URL(config.endpoint, window.location.href);
		Object.keys(args).forEach(function (key) {
			url.searchParams.set(key, args[key]);
		});

		root.classList.add('is-loading');
		root.setAttribute('aria-busy', 'true');
		var loading = root.querySelector('.sln-weekly-schedule__loading');
		if (loading) {
			loading.textContent = config.loading || '';
		}

		fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then(function (response) {
				if (!response.ok) {
					throw new Error(response.status);
				}
				return response.json();
			})
			.then(function (data) {
				var wrapper = document.createElement('div');
				wrapper.innerHTML = data.html;
				var fresh = wrapper.firstElementChild;
				if (!fresh) {
					throw new Error('empty');
				}
				// Keep the DOM ids of this instance, so two widgets on the same page
				// never share ids and page anchors/custom CSS keep working.
				renameIds(fresh, fresh.id, root.id);
				root.parentNode.replaceChild(fresh, root);
				init(fresh);
				var range = fresh.querySelector('.sln-weekly-schedule__range');
				if (range) {
					range.setAttribute('tabindex', '-1');
					range.focus();
				}
			})
			.catch(function () {
				root.classList.remove('is-loading');
				root.removeAttribute('aria-busy');
				var loading = root.querySelector('.sln-weekly-schedule__loading');
				if (loading) {
					loading.textContent = '';
				}
				showError(root);
			});
	}

	function renameIds(fresh, from, to) {
		var nodes = [fresh].concat(Array.prototype.slice.call(fresh.querySelectorAll('[id],[aria-controls],[aria-labelledby]')));
		nodes.forEach(function (node) {
			['id', 'aria-controls', 'aria-labelledby'].forEach(function (attr) {
				var value = node.getAttribute(attr);
				if (value && value.indexOf(from) === 0) {
					node.setAttribute(attr, to + value.slice(from.length));
				}
			});
		});
	}

	function showError(root) {
		var box = root.querySelector('.sln-weekly-schedule__error');
		if (!box) {
			box = document.createElement('p');
			box.className = 'sln-weekly-schedule__error';
			box.setAttribute('role', 'alert');
			root.querySelector('.sln-weekly-schedule__header').insertAdjacentElement('afterend', box);
		}
		box.textContent = config.error || 'Error';
	}

	function init(root) {
		if (root.getAttribute('data-sln-ws-ready')) {
			return;
		}
		root.setAttribute('data-sln-ws-ready', '1');
		root.classList.add('is-enhanced');

		tabs(root).forEach(function (tab) {
			tab.addEventListener('click', function () {
				selectTab(root, tab, false);
			});
		});
		var tablist = root.querySelector('[role="tablist"]');
		if (tablist) {
			tablist.addEventListener('keydown', function (event) {
				onTabKeydown(root, event);
			});
		}

		Array.prototype.forEach.call(root.querySelectorAll('[data-sln-ws-goto]'), function (button) {
			button.addEventListener('click', function () {
				var week = button.getAttribute('data-sln-ws-goto');
				if (week && config.endpoint) {
					loadWeek(root, week);
				}
			});
		});
	}

	function initAll(context) {
		Array.prototype.forEach.call((context || document).querySelectorAll('.sln-weekly-schedule'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initAll();
		});
	} else {
		initAll();
	}

	// Elementor editor and popups render widgets after page load.
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/sln_weekly_schedule.default', function ($scope) {
				initAll($scope[0] || $scope);
			});
		}
	});
})();
