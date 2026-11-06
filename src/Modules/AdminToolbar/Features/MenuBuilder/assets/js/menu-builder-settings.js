/**
 * Admin Toolbar Menu Builder settings tab.
 *
 * Builds the pool of shortcuts and the menu tree from the JSON the tab renders, and writes the
 * tree back to a hidden field as nested JSON on every change. The tree is kept as a flat list of
 * nodes with a depth; a node's children are the nodes after it that are deeper.
 *
 * @since 1.0.0
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		const root = document.querySelector('.js-dwp-menu-builder');
		if (!root) {
			return;
		}

		const data       = JSON.parse(root.querySelector('.js-dwp-mb-data').textContent);
		const t          = data.i18n;
		const maxDepth   = data.maxDepth;
		const pool       = root.querySelector('.js-dwp-mb-pool');
		const tree       = root.querySelector('.js-dwp-mb-tree');
		const tools      = root.querySelector('.js-dwp-mb-pool-tools');
		const serialized = root.querySelector('.js-dwp-mb-serialized');
		const form       = root.closest('form');

		// The data behind each pool item and tree node, and the row each tree node was made from.
		const rows    = new WeakMap();
		const sources = new WeakMap();

		let dragged = null; // { origin: 'pool' | 'tree', nodes: Element[] }

		/* ------------------------------------------------------------------ helpers */

		function el(tag, className, text) {
			const element = document.createElement(tag);
			if (className) {
				element.className = className;
			}
			if (undefined !== text) {
				element.textContent = text;
			}
			return element;
		}

		function iconButton(icon, label, className) {
			const button = el('button', 'dwp-mb-icon-button ' + className);
			button.type = 'button';
			button.title = label;
			button.setAttribute('aria-label', label);
			button.appendChild(el('span', 'dashicons ' + icon));
			return button;
		}

		function badge(modifier, text, tip) {
			const element = el('span', 'dwp-mb-badge dwp-mb-badge--' + modifier, text);
			if (tip) {
				element.title = tip;
			}
			return element;
		}

		function statusText(status) {
			return {
				'active': t.statusActive,
				'inactive': t.statusInactive,
				'not-installed': t.statusMissing,
			}[status] || t.statusUnknown;
		}

		function newInstanceId() {
			return 'inst-' + Math.random().toString(36).slice(2, 11);
		}

		/* ------------------------------------------------------------------ badges */

		// Source, link kind (admin or front end) and, if not active, the provider status.
		function metaLine(row) {
			const line = el('span', 'dwp-mb-meta');
			line.appendChild(el('span', 'dwp-mb-source', row.providerName));

			if ('admin' === row.kind) {
				const kind = badge('admin', t.kindAdmin, t.kindAdminTip);
				kind.prepend(el('span', 'dashicons dashicons-dashboard'));
				line.appendChild(kind);
			} else if ('frontend' === row.kind) {
				const kind = badge('frontend', t.kindFrontend, t.kindFrontendTip);
				kind.prepend(el('span', 'dashicons dashicons-admin-home'));
				line.appendChild(kind);
			}

			if ('active' !== row.status) {
				line.appendChild(badge(row.status, statusText(row.status), t.inactiveHint));
			}

			return line;
		}

		function visibilityLabel(visibility) {
			return { 'admin': t.kindAdmin, 'frontend': t.kindFrontend }[visibility] || t.visibleBothShort;
		}

		// One-click choice where a node is shown. A node is shown only where both its parents and
		// the node itself allow it, so under a restricted parent the node keeps its own choice (it
		// usually follows from where its link goes); a choice that conflicts with a parent (never
		// shown) is marked with a warning next to the source, not changed.
		function createVisibilityControl() {
			const wrapper = el('span', 'dwp-mb-visibility');
			const group   = el('span', 'dwp-mb-segmented');
			group.setAttribute('role', 'group');
			group.setAttribute('aria-label', t.visibility);

			[ [ 'admin', t.visibleAdmin ], [ 'both', t.visibleBoth ], [ 'frontend', t.visibleFrontend ] ].forEach(function (option) {
				const button = el('button', 'dwp-mb-segment', visibilityLabel(option[ 0 ]));
				button.type = 'button';
				button.title = option[ 1 ];
				button.dataset.visibility = option[ 0 ];
				group.appendChild(button);
			});

			wrapper.appendChild(group);
			return wrapper;
		}

		// One-click choice which of its own titles a shortcut shows (when its title field is empty):
		// automatic, short, full, or the name of its source. Stored as '' for automatic.
		const titleForms = [
			[ '', t.titleFormAuto, t.titleFormAutoTip ],
			[ 'short', t.titleFormShort, t.titleFormShortTip ],
			[ 'full', t.titleFormFull, t.titleFormFullTip ],
			[ 'source', t.titleFormSource, t.titleFormSourceTip ],
		];

		function createTitleFormControl() {
			const group = el('span', 'dwp-mb-segmented dwp-mb-title-form');
			group.setAttribute('role', 'group');
			group.setAttribute('aria-label', t.titleForm);

			titleForms.forEach(function (option) {
				const button = el('button', 'dwp-mb-segment', option[ 1 ]);
				button.type = 'button';
				button.title = option[ 2 ];
				button.dataset.titleForm = option[ 0 ];
				group.appendChild(button);
			});

			return group;
		}

		// Sets the title form of a shortcut (custom submenus have none) and shows the resulting title.
		function setTitleForm(node, form) {
			const state = rows.get(node);
			if ('shortcut' !== state.type) {
				return;
			}
			state.titleForm = form;
			refreshHeader(node);
		}

		function refreshTitleForm(node) {
			const state = rows.get(node);
			node.querySelectorAll('.dwp-mb-edit [data-title-form]').forEach(function (button) {
				button.setAttribute('aria-pressed', String(button.dataset.titleForm === state.titleForm));
			});
		}

		function refreshVisibility(node, restriction) {
			const state    = rows.get(node);
			const warning  = node.querySelector('.dwp-mb-conflict');
			const conflict = '' !== restriction && 'both' !== state.visibility && restriction !== state.visibility;

			node.querySelectorAll('.dwp-mb-segment[data-visibility]').forEach(function (button) {
				button.setAttribute('aria-pressed', String(button.dataset.visibility === state.visibility));
			});

			node.classList.toggle('has-visibility-conflict', conflict);
			warning.hidden = !conflict;
			warning.title = conflict ? t.visibilityConflict.replace('%s', visibilityLabel(state.visibility)) : '';
		}

		/* ------------------------------------------------------------------ pool */

		function createPoolItem(row) {
			const item = el('div', 'dwp-mb-node dwp-mb-node--pool js-dwp-mb-pool-item');
			item.draggable = true;
			item.dataset.provider = row.provider;
			item.dataset.type = row.type;
			if ('active' !== row.status) {
				item.classList.add('is-inactive');
			}
			if (row.label) {
				item.title = row.label;
			}

			const checkbox = el('input', 'js-dwp-mb-check');
			checkbox.type = 'checkbox';
			checkbox.setAttribute('aria-label', row.defaultTitle);

			const body = el('span', 'dwp-mb-node-body');
			const meta = metaLine(row);
			body.appendChild(el('strong', 'dwp-mb-title', row.defaultTitle));
			body.appendChild(meta);

			// Where the shortcut links to, on hover: titles alone are not always enough.
			if (row.href) {
				const link = el('span', 'dashicons dashicons-admin-links dwp-mb-url');
				link.title = row.href;
				link.setAttribute('aria-label', row.href);
				meta.appendChild(link);
			}

			item.append(checkbox, body);
			rows.set(item, row);
			return item;
		}

		// Remembered in the browser: which pool groups and which tree branches are open.
		function loadSet(key) {
			try {
				return new Set(JSON.parse(window.localStorage.getItem(key) || '[]'));
			} catch (error) {
				return new Set();
			}
		}

		function saveSet(key, set) {
			try {
				window.localStorage.setItem(key, JSON.stringify([ ...set ]));
			} catch (error) {
				// Storage unavailable: the state is simply not remembered.
			}
		}

		const openGroups = loadSet('dwp-mb-open-groups');
		const groups     = []; // { element: details, count: span }

		// The pool: the custom submenu first, then one collapsible group per provider.
		function buildPool() {
			const providers = new Map(data.providers.map(function (provider) {
				return [ provider.id, provider ];
			}));
			const lists = new Map();

			data.pool.forEach(function (row) {
				const item = createPoolItem(row);
				if (!row.provider) {
					pool.appendChild(item);
					return;
				}

				if (!lists.has(row.provider)) {
					const provider = providers.get(row.provider) || { id: row.provider, name: row.providerName, status: row.status };
					const group    = el('details', 'dwp-mb-group');
					const summary  = el('summary', 'dwp-mb-group-summary');
					const count    = el('span', 'dwp-mb-group-count');
					const check    = el('input', 'dwp-mb-group-check js-dwp-mb-group-check');

					// Checks or unchecks the group's items that the filter shows, open or closed;
					// a checkbox in the heading does not open or close the group.
					check.type = 'checkbox';
					check.title = t.selectGroup.replace('%s', provider.name);
					check.setAttribute('aria-label', check.title);
					check.addEventListener('change', function () {
						group.querySelectorAll('.js-dwp-mb-pool-item:not([hidden]) .js-dwp-mb-check').forEach(function (checkbox) {
							checkbox.checked = check.checked;
						});
						updateGroupChecks();
					});

					group.dataset.provider = provider.id;
					group.open = openGroups.has(provider.id);
					summary.draggable = true;
					summary.title = t.dragGroup.replace('%s', provider.name);
					summary.append(check, el('span', 'dwp-mb-group-name', provider.name), count);
					if ('active' !== provider.status) {
						summary.appendChild(badge(provider.status, statusText(provider.status), t.inactiveHint));
					}
					group.appendChild(summary);
					group.addEventListener('toggle', function () {
						if (!searchInput.value.trim()) {
							if (group.open) {
								openGroups.add(provider.id);
							} else {
								openGroups.delete(provider.id);
							}
							saveSet('dwp-mb-open-groups', openGroups);
						}
					});

					groups.push({ element: group, count: count });
					lists.set(row.provider, group);
					pool.appendChild(group);
				}
				lists.get(row.provider).appendChild(item);
			});
		}

		// Search (title, source, description, id, URL) and "only active"; while searching, groups with
		// a match open, and the remembered state returns when the search is cleared.
		function filterPool() {
			const query      = searchInput.value.trim().toLowerCase();
			const activeOnly = isActiveOnly();

			pool.querySelectorAll('.js-dwp-mb-pool-item').forEach(function (item) {
				const row  = rows.get(item);
				const text = [ row.defaultTitle, row.providerName, row.label, row.id, row.href ].join(' ').toLowerCase();
				item.hidden = (activeOnly && 'active' !== row.status) || ('' !== query && !text.includes(query));
			});

			groups.forEach(function (group) {
				const visible = group.element.querySelectorAll('.js-dwp-mb-pool-item:not([hidden])').length;
				group.element.hidden = 0 === visible;
				group.count.textContent = String(visible);
				group.element.open = '' !== query ? visible > 0 : openGroups.has(group.element.dataset.provider);
			});
			updateGroupChecks();
		}

		// A group's checkbox is checked when all its shown items are, half when some are.
		function updateGroupChecks() {
			groups.forEach(function (group) {
				const boxes   = [ ...group.element.querySelectorAll('.js-dwp-mb-pool-item:not([hidden]) .js-dwp-mb-check') ];
				const checked = boxes.filter(function (box) {
					return box.checked;
				}).length;
				const check   = group.element.querySelector('.js-dwp-mb-group-check');

				check.checked       = boxes.length > 0 && checked === boxes.length;
				check.indeterminate = checked > 0 && checked < boxes.length;
			});
		}

		pool.addEventListener('change', function (event) {
			if (event.target.classList.contains('js-dwp-mb-check')) {
				updateGroupChecks();
			}
		});

		const searchInput = el('input', 'dwp-mb-search');
		searchInput.type = 'search';
		searchInput.placeholder = t.search;
		searchInput.setAttribute('aria-label', t.search);
		searchInput.addEventListener('input', function () {
			clearButton.hidden = '' === searchInput.value;
			filterPool();
		});

		// A small × inside the search field empties it, shown only when there is something to clear.
		const clearButton = el('button', 'dwp-mb-search-clear');
		clearButton.type = 'button';
		clearButton.hidden = true;
		clearButton.title = t.clearSearch;
		clearButton.setAttribute('aria-label', t.clearSearch);
		clearButton.appendChild(el('span', 'dashicons dashicons-no-alt'));
		clearButton.addEventListener('click', function () {
			searchInput.value = '';
			clearButton.hidden = true;
			filterPool();
			searchInput.focus();
		});

		const searchField = el('span', 'dwp-mb-search-field');
		searchField.append(searchInput, clearButton);

		// One row: a filter switch for "only active", search and Add (for what is checked).
		const activeButton = el('button', 'button dwp-mb-filter');
		activeButton.type = 'button';
		activeButton.title = t.onlyActive;
		activeButton.setAttribute('aria-label', t.onlyActive);
		activeButton.setAttribute('aria-pressed', 'false');
		activeButton.appendChild(el('span', 'dashicons dashicons-filter'));
		activeButton.addEventListener('click', function () {
			activeButton.setAttribute('aria-pressed', String(!isActiveOnly()));
			filterPool();
		});

		function isActiveOnly() {
			return 'true' === activeButton.getAttribute('aria-pressed');
		}

		const addButton = el('button', 'button button-secondary', t.addSelected);
		addButton.type = 'button';
		addButton.title = t.addSelectedTip;

		tools.append(activeButton, searchField, addButton);

		// With exactly one node selected in the menu, new nodes go below it: inside it when it is a
		// submenu without a link of its own, otherwise after its branch. Without one, at the end.
		function insertionPoint() {
			if (1 !== selected.size) {
				return { before: null, depth: insertionDepth(null) };
			}

			const target = [ ...selected ][ 0 ];
			const branch = branchOf(target);
			const state  = rows.get(target);
			const before = branch[ branch.length - 1 ].nextElementSibling;
			const inside = 'submenu' === state.type && '' === state.href && depthOf(target) < maxDepth;

			if (inside) {
				setCollapsed(target, false);
			}
			return { before: before, depth: depthOf(target) + (inside ? 1 : 0) };
		}

		addButton.addEventListener('click', function () {
			const checked = [ ...pool.querySelectorAll('.js-dwp-mb-check:checked') ];
			if (!checked.length) {
				window.alert(t.selectFirst);
				return;
			}

			const point = insertionPoint();
			insertRows(checked.map(function (checkbox) {
				checkbox.checked = false;
				return rows.get(checkbox.closest('.js-dwp-mb-pool-item'));
			}), point.before, point.depth);
			updateGroupChecks();
		});

		/* ------------------------------------------------------------------ tree */

		function treeNodes() {
			return [ ...tree.querySelectorAll('.js-dwp-mb-tree-node') ];
		}

		function depthOf(node) {
			return parseInt(node.dataset.depth, 10) || 0;
		}

		function setDepth(node, depth) {
			node.dataset.depth = String(depth);
			node.style.setProperty('--depth', String(depth));
			node.setAttribute('aria-level', String(depth + 1));
		}

		// The node and the nodes below it.
		function branchOf(node) {
			const nodes  = treeNodes();
			const start  = nodes.indexOf(node);
			const branch = [ node ];
			for (let i = start + 1; i < nodes.length && depthOf(nodes[ i ]) > depthOf(node); i++) {
				branch.push(nodes[ i ]);
			}
			return branch;
		}

		// The title the node gets without one of its own, in its title form. Automatic: the short title
		// under a parent of the same group ("New" under Plugins), else the full title. serialize() keeps
		// parentGroup current. MenuBuilder::get_shortcut_title() applies the same rule in the toolbar.
		function ownDefaultTitle(state) {
			if ('submenu' === state.type) {
				return t.submenuTitle;
			}
			switch (state.titleForm) {
				case 'short':
					return state.shortTitle || state.defaultTitle;
				case 'full':
					return state.defaultTitle;
				case 'source':
					return state.sourceName || state.defaultTitle;
			}
			return state.shortTitle && state.parentGroup && state.group === state.parentGroup
				? state.shortTitle
				: state.defaultTitle;
		}

		function displayTitle(state) {
			return state.title || ownDefaultTitle(state);
		}

		function refreshHeader(node) {
			const state = rows.get(node);
			node.querySelector('.dwp-mb-title').textContent = displayTitle(state);
			node.querySelector('.dwp-mb-edit .regular-text').placeholder = ownDefaultTitle(state);
			refreshTitleForm(node);
		}

		function field(labelText, input, hint) {
			const wrapper = el('div', 'dwp-mb-field');
			const label   = el('label', '', labelText);
			input.id      = 'dwp-mb-' + Math.random().toString(36).slice(2, 11);
			label.htmlFor = input.id;
			wrapper.append(label, input);
			if (hint) {
				wrapper.appendChild(el('p', 'description', hint));
			}
			return wrapper;
		}

		function createEditPanel(node, state) {
			const panel = el('div', 'dwp-mb-edit');
			panel.hidden = true;

			const title = el('input', 'regular-text');
			title.type = 'text';
			title.value = state.title;
			title.placeholder = ownDefaultTitle(state);
			title.addEventListener('input', function () {
				state.title = title.value.trim();
				refreshHeader(node);
				typingIn = node;
				serialize();
			});
			panel.appendChild(field(t.title, title, 'submenu' === state.type ? '' : t.titleHint));

			if ('shortcut' === state.type) {
				const formField = el('div', 'dwp-mb-field');
				const forms     = createTitleFormControl();
				const short     = forms.querySelector('[data-title-form="short"]');
				short.disabled  = !state.shortTitle; // Without a short title, Short would show the full one.
				formField.append(el('span', 'dwp-mb-field-label', t.titleForm), forms, el('p', 'description', t.titleFormHint));
				panel.appendChild(formField);
			}

			const url = el('input', 'regular-text code');
			url.type = 'text';
			url.value = state.href;
			if ('submenu' === state.type) {
				url.addEventListener('input', function () {
					state.href = url.value.trim();
					typingIn = node;
					serialize();
				});
				panel.appendChild(field(t.url, url, t.urlHint));
			} else {
				url.readOnly = true;
				const copy = el('button', 'button button-small', t.copy);
				copy.type = 'button';
				copy.addEventListener('click', function () {
					navigator.clipboard.writeText(url.value).then(function () {
						copy.textContent = t.copied;
						window.setTimeout(function () {
							copy.textContent = t.copy;
						}, 1500);
					});
				});
				const urlField = field(t.url, url);
				urlField.appendChild(copy);
				urlField.classList.add('dwp-mb-field--url');
				panel.appendChild(urlField);
			}

			return panel;
		}

		function createTreeNode(row, depth) {
			const state = {
				id: row.id,
				type: row.type,
				defaultTitle: row.defaultTitle,
				shortTitle: row.shortTitle || '',
				group: 'shortcut' === row.type ? (row.group || row.provider || '') : '',
				parentGroup: '',
				sourceName: 'shortcut' === row.type && row.provider ? row.providerName : '',
				title: row.title || '',
				titleForm: 'shortcut' === row.type && [ 'short', 'full', 'source' ].includes(row.titleForm) ? row.titleForm : '',
				href: row.href || '',
				visibility: row.visibility || 'both',
			};

			const node = el('div', 'dwp-mb-node dwp-mb-node--tree js-dwp-mb-tree-node');
			node.draggable = true;
			node.tabIndex = -1;
			node.setAttribute('role', 'treeitem');
			node.setAttribute('aria-selected', 'false');
			node.dataset.instance = row.instance || newInstanceId();
			if ('submenu' === row.type) {
				node.classList.add('is-submenu');
			}
			if ('active' !== row.status) {
				node.classList.add('is-inactive');
			}
			setDepth(node, depth);
			rows.set(node, state);
			sources.set(node, row);

			// Two rows in a grid: toggle, checkbox, title and buttons; below them the source and
			// badges, and the visibility switch under the buttons.
			const header = el('div', 'dwp-mb-node-header dwp-mb-node-header--tree');
			const toggle = iconButton('dashicons-arrow-down-alt2', t.collapse, 'dwp-mb-collapse js-dwp-mb-collapse');
			toggle.tabIndex = -1; // The arrow keys collapse and expand.

			// Out of the tab order: the node itself takes the focus and Space selects it.
			const select = el('input', 'dwp-mb-select js-dwp-mb-select');
			select.type = 'checkbox';
			select.tabIndex = -1;
			select.title = t.select;
			select.setAttribute('aria-label', t.select);

			const controls = el('span', 'dwp-mb-controls');
			controls.append(
				iconButton('dashicons-arrow-left-alt2', t.moveOut, 'js-dwp-mb-out'),
				iconButton('dashicons-arrow-right-alt2', t.moveIn, 'js-dwp-mb-in'),
				iconButton('dashicons-admin-generic', t.edit, 'js-dwp-mb-edit'),
				iconButton('dashicons-trash', t.remove, 'js-dwp-mb-remove'),
				el('span', 'dashicons dashicons-menu dwp-mb-handle')
			);

			const meta    = metaLine(row);
			const warning = el('span', 'dwp-mb-conflict', '⚠');
			warning.hidden = true;
			meta.appendChild(warning);

			header.append(toggle, select, el('strong', 'dwp-mb-title'), controls, meta, createVisibilityControl());
			node.append(header, createEditPanel(node, state));
			refreshHeader(node);
			return node;
		}

		function updateEmptyState() {
			let message = tree.querySelector('.dwp-mb-empty');
			if (treeNodes().length) {
				if (message) {
					message.remove();
				}
			} else if (!message) {
				message = el('p', 'dwp-mb-empty', t.emptyTree);
				tree.appendChild(message);
			}
		}

		// New nodes take the depth of the node they are inserted before, so they never take over
		// that node's children; at the end they take the depth of the last visible node (not of a
		// node hidden in a collapsed branch, which would put them inside that branch unseen).
		// Nodes in "ignore" (a branch being moved) do not count.
		function insertionDepth(before, ignore) {
			if (before) {
				return depthOf(before);
			}
			const nodes = visibleNodes().filter(function (node) {
				return !ignore || !ignore.includes(node);
			});
			return nodes.length ? depthOf(nodes[ nodes.length - 1 ]) : 0;
		}

		// Opens the collapsed branches around a node, so a node that is moved or added never
		// disappears into a closed branch.
		function reveal(node) {
			const nodes = treeNodes();
			let depth   = depthOf(node);
			let changed = false;

			for (let i = nodes.indexOf(node) - 1; i >= 0 && depth > 0; i--) {
				if (depthOf(nodes[ i ]) < depth) {
					depth = depthOf(nodes[ i ]);
					if (!expanded.has(nodes[ i ].dataset.instance)) {
						expanded.add(nodes[ i ].dataset.instance);
						changed = true;
					}
				}
			}

			if (changed) {
				saveSet('dwp-mb-expanded', expanded);
				refreshTreeState();
			}
		}

		// A row may carry "offset": how many levels below the first row it goes (a submenu's items).
		function insertRows(newRows, before, depth) {
			const at    = undefined === depth ? insertionDepth(before) : depth;
			const added = newRows.map(function (row) {
				const node = createTreeNode(row, Math.min(maxDepth, at + (row.offset || 0)));
				tree.insertBefore(node, before);
				if ('submenu' === row.type) {
					node.querySelector('.dwp-mb-edit').hidden = false;
				}
				return node;
			});
			updateEmptyState();
			serialize();
			if (added.length) {
				reveal(added[ 0 ]);
				added[ 0 ].scrollIntoView({ block: 'nearest' });
			}
			return added;
		}

		/* ------------------------------------------------------------------ collapsing branches */

		// Branches start collapsed; the browser remembers which ones were opened.
		const expanded = loadSet('dwp-mb-expanded'); // Instance ids of expanded nodes.

		function setCollapsed(node, on) {
			if (on) {
				expanded.delete(node.dataset.instance);
			} else {
				expanded.add(node.dataset.instance);
			}
			saveSet('dwp-mb-expanded', expanded);
			refreshTreeState();
		}

		// Expands every branch, or collapses them all when every branch is open already.
		function toggleAllBranches() {
			const parents = treeNodes().filter(function (node) {
				return node.classList.contains('has-children');
			});
			const expand  = parents.some(function (node) {
				return node.classList.contains('is-collapsed');
			});

			parents.forEach(function (node) {
				if (expand) {
					expanded.add(node.dataset.instance);
				} else {
					expanded.delete(node.dataset.instance);
				}
			});
			saveSet('dwp-mb-expanded', expanded);
			refreshTreeState();
		}

		// Marks the nodes that have children and hides the branches of collapsed nodes.
		function refreshTreeState() {
			const nodes = treeNodes();
			let hiddenBelow = -1; // Nodes deeper than this depth lie in a collapsed branch.

			nodes.forEach(function (node, index) {
				const depth       = depthOf(node);
				const next        = nodes[ index + 1 ];
				const hasChildren = !!next && depthOf(next) > depth;
				const isCollapsed = hasChildren && !expanded.has(node.dataset.instance);
				const toggle      = node.querySelector('.js-dwp-mb-collapse');

				if (hiddenBelow >= 0 && depth <= hiddenBelow) {
					hiddenBelow = -1;
				}
				node.hidden = hiddenBelow >= 0;
				node.classList.toggle('has-children', hasChildren);
				node.classList.toggle('is-collapsed', isCollapsed);
				toggle.title = isCollapsed ? t.expand : t.collapse;
				toggle.setAttribute('aria-label', toggle.title);
				toggle.setAttribute('aria-expanded', String(!isCollapsed));
				if (hasChildren) {
					node.setAttribute('aria-expanded', String(!isCollapsed));
				} else {
					node.removeAttribute('aria-expanded');
				}

				if (isCollapsed && hiddenBelow < 0) {
					hiddenBelow = depth;
				}
			});

			ensureTabStop();
			updateBranchesButton();
		}

		// Moves a branch in or out by one level, if the result is still a valid tree. Moving out
		// places the branch after its old parent's branch, so it does not take over its siblings.
		function shiftBranch(node, delta) {
			const branch   = branchOf(node);
			const nodes    = treeNodes();
			const index    = nodes.indexOf(node);
			const previous = nodes[ index - 1 ];
			const newDepth = depthOf(node) + delta;
			const deepest  = Math.max(...branch.map(depthOf)) + delta;

			if (newDepth < 0 || deepest > maxDepth) {
				return;
			}
			if (delta > 0 && (!previous || newDepth > depthOf(previous) + 1)) {
				return;
			}

			if (delta < 0) {
				const parent = nodes.slice(0, index).reverse().find(function (item) {
					return depthOf(item) < depthOf(node);
				});
				const parentBranch = branchOf(parent);
				const anchor       = parentBranch[ parentBranch.length - 1 ].nextElementSibling;
				branch.forEach(function (item) {
					tree.insertBefore(item, anchor);
				});
			}

			branch.forEach(function (item) {
				setDepth(item, depthOf(item) + delta);
			});
			serialize();
			reveal(node);
		}

		function removeNodes(nodes) {
			nodes.forEach(function (item) {
				selected.delete(item);
				item.remove();
			});
			updateEmptyState();
			updateBulkBar();
			serialize();
		}

		function removeBranch(node) {
			const branch = branchOf(node);
			if (branch.length > 1 && !window.confirm(t.removeConfirm.replace('%d', String(branch.length - 1)))) {
				return;
			}
			removeNodes(branch);
		}

		/* ------------------------------------------------------------------ selection and bulk actions */

		const selected = new Set();
		let anchor     = null; // The node a shift-click range starts from.

		function setSelected(node, on) {
			if (on) {
				selected.add(node);
			} else {
				selected.delete(node);
			}
			node.classList.toggle('is-selected', on);
			node.setAttribute('aria-selected', String(on));
			node.querySelector('.js-dwp-mb-select').checked = on;
		}

		function clearSelection() {
			[ ...selected ].forEach(function (node) {
				setSelected(node, false);
			});
			updateBulkBar();
		}

		// Selects or deselects a node together with the nodes below it.
		function toggleBranchSelection(node) {
			const on = !selected.has(node);
			branchOf(node).forEach(function (item) {
				setSelected(item, on);
			});
			anchor = node;
			updateBulkBar();
		}

		// Plain click toggles a node, Ctrl-click the node with its branch, and shift-click selects
		// the range from the last clicked node.
		function clickSelect(node, range, branch) {
			const nodes = treeNodes();
			if (branch) {
				toggleBranchSelection(node);
				return;
			}
			if (range && anchor && nodes.includes(anchor)) {
				const from = nodes.indexOf(anchor);
				const to   = nodes.indexOf(node);
				nodes.slice(Math.min(from, to), Math.max(from, to) + 1).forEach(function (item) {
					if (!item.hidden) {
						setSelected(item, true);
					}
				});
			} else {
				setSelected(node, !selected.has(node));
			}
			anchor = node;
			updateBulkBar();
		}

		// The selected nodes in tree order, leaving out those that lie in the branch of another
		// selected node: moving or removing a node always takes its branch along.
		function selectedRoots() {
			const roots = [];
			let coveredBelow = -1; // Nodes deeper than this depth lie in the branch of the last root.

			treeNodes().forEach(function (node) {
				if (coveredBelow >= 0 && depthOf(node) > coveredBelow) {
					return;
				}
				coveredBelow = -1;
				if (selected.has(node)) {
					roots.push(node);
					coveredBelow = depthOf(node);
				}
			});
			return roots;
		}

		function branchesOf(roots) {
			return roots.flatMap(branchOf);
		}

		const bulkBar   = el('div', 'dwp-mb-bulk');
		const bulkCount = el('span', 'dwp-mb-bulk-count');
		const bulkShow  = createVisibilityControl();
		bulkShow.prepend(el('span', 'dwp-mb-bulk-label', t.visibility));
		const bulkForm  = el('span', 'dwp-mb-bulk-title-form');
		bulkForm.append(el('span', 'dwp-mb-bulk-label', t.titleForm), createTitleFormControl());

		function bulkButton(text, className, tip) {
			const button = el('button', 'button button-small ' + className, text);
			button.type = 'button';
			if (tip) {
				button.title = tip;
			}
			return button;
		}

		bulkBar.append(
			bulkCount,
			bulkShow,
			bulkForm,
			bulkButton(t.selectBelow, 'js-dwp-mb-bulk-below', t.selectBelowTip),
			bulkButton(t.resetTitles, 'js-dwp-mb-bulk-reset', t.resetTitlesTip),
			bulkButton(t.remove, 'js-dwp-mb-bulk-remove'),
			bulkButton(t.clearSelection, 'js-dwp-mb-bulk-clear')
		);
		bulkBar.hidden = true;
		tree.before(bulkBar);

		function updateBulkBar() {
			bulkBar.hidden = 0 === selected.size;
			bulkCount.textContent = t.selectedCount.replace('%d', String(selected.size));
		}

		bulkBar.addEventListener('click', function (event) {
			const button = event.target.closest('button');
			if (!button) {
				return;
			}

			if (button.dataset.visibility) {
				selected.forEach(function (node) {
					rows.get(node).visibility = button.dataset.visibility;
				});
				serialize();
			} else if (undefined !== button.dataset.titleForm) {
				selected.forEach(function (node) {
					setTitleForm(node, button.dataset.titleForm);
				});
				serialize();
			} else if (button.classList.contains('js-dwp-mb-bulk-below')) {
				branchesOf(selectedRoots()).forEach(function (node) {
					setSelected(node, true);
				});
				updateBulkBar();
			} else if (button.classList.contains('js-dwp-mb-bulk-reset')) {
				selected.forEach(function (node) {
					const state = rows.get(node);
					if ('shortcut' === state.type) {
						state.title     = '';
						state.titleForm = '';
						node.querySelector('.dwp-mb-edit input[type="text"]').value = '';
						refreshHeader(node);
					}
				});
				serialize();
			} else if (button.classList.contains('js-dwp-mb-bulk-remove')) {
				const nodes = branchesOf(selectedRoots());
				if (window.confirm(t.removeSelectedConfirm.replace('%d', String(nodes.length)))) {
					removeNodes(nodes);
				}
			} else if (button.classList.contains('js-dwp-mb-bulk-clear')) {
				clearSelection();
			}
		});

		tree.addEventListener('click', function (event) {
			const node = event.target.closest('.js-dwp-mb-tree-node');
			if (node && event.target.classList.contains('js-dwp-mb-select')) {
				clickSelect(node, event.shiftKey, event.ctrlKey || event.metaKey);
				return;
			}

			const button = event.target.closest('button');
			if (!button || !node) {
				return;
			}

			if (button.dataset.visibility) {
				rows.get(node).visibility = button.dataset.visibility;
				serialize();
			} else if (undefined !== button.dataset.titleForm) {
				setTitleForm(node, button.dataset.titleForm);
				serialize();
			} else if (button.classList.contains('js-dwp-mb-collapse')) {
				setCollapsed(node, !node.classList.contains('is-collapsed'));
			} else if (button.classList.contains('js-dwp-mb-out')) {
				shiftBranch(node, -1);
			} else if (button.classList.contains('js-dwp-mb-in')) {
				shiftBranch(node, 1);
			} else if (button.classList.contains('js-dwp-mb-edit')) {
				const panel = node.querySelector('.dwp-mb-edit');
				panel.hidden = !panel.hidden;
				button.classList.toggle('is-open', !panel.hidden);
			} else if (button.classList.contains('js-dwp-mb-remove')) {
				removeBranch(node);
			}
		});

		/* ------------------------------------------------------------------ drag and drop */

		// Where a drop lands: before which node, and at what depth. The depth follows the pointer
		// sideways: one indent to the right of where the drag started (or entered the tree) is one
		// level deeper. It stays between the depth of the node below (so that node's place in the
		// menu does not change) and one deeper than the node above.
		const dropLine = el('div', 'dwp-mb-drop-line');
		let dropTarget = null; // { before: Element|null, depth: number }
		let dragStartX = null;

		function indentWidth() {
			return parseFloat(getComputedStyle(tree).getPropertyValue('--dwp-mb-indent')) || 28;
		}

		function nodeAbove(before) {
			const nodes = visibleNodes().filter(function (node) {
				return !dragged.nodes.includes(node);
			});
			const index = before ? nodes.indexOf(before) : nodes.length;
			return index > 0 ? nodes[ index - 1 ] : null;
		}

		function updateDropTarget(event) {
			const before = nodeAfterPointer(event.clientY);
			const above  = nodeAbove(before);
			const min    = before ? depthOf(before) : 0;
			const max    = above ? Math.min(maxDepth, depthOf(above) + 1) : 0;
			const shift  = Math.round((event.clientX - dragStartX) / indentWidth());
			const depth  = Math.max(min, Math.min(max, insertionDepth(before, dragged.nodes) + shift));

			dropTarget = { before: before, depth: depth };

			const style = getComputedStyle(tree);
			const top   = before
				? before.offsetTop - 4
				: (above ? above.offsetTop + above.offsetHeight + 2 : parseFloat(style.paddingTop));
			dropLine.style.top = top + 'px';
			dropLine.style.left = (parseFloat(style.paddingLeft) + depth * indentWidth()) + 'px';
			if (!dropLine.isConnected) {
				tree.appendChild(dropLine);
			}
		}

		function clearDropMarker() {
			dropLine.remove();
		}

		// The first node (outside the dragged branch) whose middle is below the pointer.
		function nodeAfterPointer(y) {
			return treeNodes().find(function (node) {
				if (dragged && dragged.nodes.includes(node)) {
					return false;
				}
				const box = node.getBoundingClientRect();
				return y < box.top + box.height / 2;
			}) || null;
		}

		root.addEventListener('dragstart', function (event) {
			if (event.target.closest('input, select, button')) {
				return;
			}

			const treeNode = event.target.closest('.js-dwp-mb-tree-node');
			const poolItem = event.target.closest('.js-dwp-mb-pool-item');
			const heading  = event.target.closest('.dwp-mb-group-summary');

			if (treeNode) {
				// Dragging a selected node drags the whole selection, each with its branch.
				const roots = selected.has(treeNode) ? selectedRoots() : [ treeNode ];
				dragged = { origin: 'tree', roots: roots, nodes: branchesOf(roots) };
			} else if (poolItem) {
				// Dragging a checked item takes all checked items along (that the filter shows).
				const check = poolItem.querySelector('.js-dwp-mb-check');
				const items = check && check.checked
					? [ ...pool.querySelectorAll('.js-dwp-mb-pool-item:not([hidden])') ].filter(function (item) {
						return item.querySelector('.js-dwp-mb-check').checked;
					})
					: [ poolItem ];
				dragged = { origin: 'pool', nodes: items, rows: items.map(function (item) {
					return rows.get(item);
				}) };
			} else if (heading) {
				// A source's heading becomes a submenu named after it, with its shortcuts (that the
				// filter shows) below it; at the deepest level only the shortcuts.
				const group = heading.closest('.dwp-mb-group');
				const items = [ ...group.querySelectorAll('.js-dwp-mb-pool-item:not([hidden])') ].map(function (item) {
					return Object.assign({}, rows.get(item), { offset: 1 });
				});
				const name  = heading.querySelector('.dwp-mb-group-name').textContent;
				dragged = { origin: 'pool', nodes: [ heading ], rows: items, submenu: Object.assign({}, customRow, { title: name }) };
			} else {
				return;
			}

			// From the pool, the sideways count starts where the pointer enters the tree.
			dragStartX = 'tree' === dragged.origin ? event.clientX : null;
			event.dataTransfer.effectAllowed = 'tree' === dragged.origin ? 'move' : 'copy';
			event.dataTransfer.setData('text/plain', '');
			dragged.nodes.forEach(function (node) {
				node.classList.add('is-dragging');
			});
		});

		root.addEventListener('dragend', function () {
			if (dragged) {
				dragged.nodes.forEach(function (node) {
					node.classList.remove('is-dragging');
				});
			}
			dragged = null;
			clearDropMarker();
		});

		tree.addEventListener('dragover', function (event) {
			if (!dragged) {
				return;
			}
			event.preventDefault();

			// Scroll the tree while dragging near its top or bottom edge.
			const box = tree.getBoundingClientRect();
			if (event.clientY < box.top + 40) {
				tree.scrollTop -= 12;
			} else if (event.clientY > box.bottom - 40) {
				tree.scrollTop += 12;
			}

			if (null === dragStartX) {
				dragStartX = event.clientX;
			}
			updateDropTarget(event);
		});

		tree.addEventListener('dragleave', function (event) {
			if (!tree.contains(event.relatedTarget)) {
				clearDropMarker();
			}
		});

		tree.addEventListener('drop', function (event) {
			if (!dragged) {
				return;
			}
			event.preventDefault();
			updateDropTarget(event);
			clearDropMarker();
			const after = dropTarget.before;
			const depth = dropTarget.depth;

			if ('pool' === dragged.origin) {
				let newRows = dragged.rows;
				if (dragged.submenu) {
					newRows = depth < maxDepth
						? [ dragged.submenu ].concat(newRows)
						: newRows.map(function (row) {
							return Object.assign({}, row, { offset: 0 });
						});
				} else {
					// Like "Add selection": what was added is no longer checked.
					dragged.nodes.forEach(function (item) {
						item.querySelector('.js-dwp-mb-check').checked = false;
					});
					updateGroupChecks();
				}
				const added = insertRows(newRows, after, depth);
				if (dragged.submenu && depth < maxDepth && added.length) {
					setCollapsed(added[ 0 ], false); // Show the shortcuts the new submenu holds.
				}
				return;
			}

			// Dropped where it already was (one branch, before its own next node, same depth): no change.
			const last = dragged.nodes[ dragged.nodes.length - 1 ];
			const next = last.nextElementSibling && last.nextElementSibling.classList.contains('js-dwp-mb-tree-node')
				? last.nextElementSibling
				: null;
			if (1 === dragged.roots.length && after === next && depth === depthOf(dragged.roots[ 0 ])) {
				return;
			}

			// Move each branch; its top node takes the depth shown by the drop line.
			dragged.roots.forEach(function (root) {
				const delta = depth - depthOf(root);
				branchOf(root).forEach(function (node) {
					tree.insertBefore(node, after);
					setDepth(node, Math.min(maxDepth, Math.max(0, depthOf(node) + delta)));
				});
			});
			serialize();
			reveal(dragged.roots[ 0 ]);
		});

		/* ------------------------------------------------------------------ keyboard */

		// One tree node is in the tab order at a time; the arrow keys move between nodes.
		tree.setAttribute('role', 'tree');
		tree.setAttribute('aria-multiselectable', 'true');
		tree.setAttribute('aria-label', t.treeLabel);

		function visibleNodes() {
			return treeNodes().filter(function (node) {
				return !node.hidden;
			});
		}

		function ensureTabStop() {
			const nodes   = visibleNodes();
			const current = nodes.find(function (node) {
				return 0 === node.tabIndex;
			});
			treeNodes().forEach(function (node) {
				node.tabIndex = node === current ? 0 : -1;
			});
			if (!current && nodes.length) {
				nodes[ 0 ].tabIndex = 0;
			}
		}

		function focusNode(node) {
			if (!node) {
				return;
			}
			treeNodes().forEach(function (item) {
				item.tabIndex = item === node ? 0 : -1;
			});
			node.focus({ preventScroll: true });
			node.scrollIntoView({ block: 'nearest' });
		}

		tree.addEventListener('focusin', function (event) {
			if (event.target.classList.contains('js-dwp-mb-tree-node')) {
				treeNodes().forEach(function (item) {
					item.tabIndex = item === event.target ? 0 : -1;
				});
			}
		});

		// What a keyboard command acts on: the selection if the focused node is part of it,
		// otherwise the focused node; always with their branches.
		function keyTargets(node) {
			return selected.has(node) ? selectedRoots() : [ node ];
		}

		// Moves a branch past its previous or next sibling (with that sibling's branch). The first
		// child moving up, or the last child moving down, leaves its parent: it becomes the sibling
		// just before or just after the parent.
		function moveBranch(node, direction) {
			const nodes  = treeNodes();
			const branch = branchOf(node);
			const depth  = depthOf(node);
			const parent = nodes.slice(0, nodes.indexOf(node)).reverse().find(function (item) {
				return depthOf(item) < depth;
			});
			let before;
			let newDepth = depth;

			if (direction < 0) {
				let sibling = null;
				for (let i = nodes.indexOf(node) - 1; i >= 0 && depthOf(nodes[ i ]) >= depth; i--) {
					if (depthOf(nodes[ i ]) === depth) {
						sibling = nodes[ i ];
						break;
					}
				}
				if (sibling) {
					before = sibling;
				} else if (parent) {
					before   = parent;
					newDepth = depthOf(parent);
				} else {
					return;
				}
			} else {
				const after = branch[ branch.length - 1 ].nextElementSibling;
				const next  = after && after.classList.contains('js-dwp-mb-tree-node') ? after : null;
				if (next && depthOf(next) === depth) {
					const siblingBranch = branchOf(next);
					before = siblingBranch[ siblingBranch.length - 1 ].nextElementSibling;
				} else if (parent) {
					before   = next; // The parent's branch ends with this branch.
					newDepth = depthOf(parent);
				} else {
					return;
				}
			}

			const delta = newDepth - depth;
			branch.forEach(function (item) {
				tree.insertBefore(item, before);
				setDepth(item, depthOf(item) + delta);
			});
		}

		function selectRange(from, to) {
			const nodes = visibleNodes();
			const a     = nodes.indexOf(from);
			const b     = nodes.indexOf(to);
			if (a < 0 || b < 0) {
				return;
			}
			nodes.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (item) {
				setSelected(item, true);
			});
			updateBulkBar();
		}

		tree.addEventListener('keydown', function (event) {
			// The keys work on a focused node, and on its checkbox after it was clicked with the
			// mouse; in the fields and buttons of a node they keep their own meaning.
			const onCheckbox = event.target.classList.contains('js-dwp-mb-select');
			const node       = event.target.closest('.js-dwp-mb-tree-node');
			if (!node || (event.target !== node && !onCheckbox)) {
				return;
			}
			if (onCheckbox && ' ' === event.key && !event.shiftKey && !event.ctrlKey && !event.metaKey) {
				return; // The checkbox toggles itself, which selects the node.
			}

			const nodes = visibleNodes();
			const index = nodes.indexOf(node);
			let handled = true;

			if (event.altKey && ('ArrowUp' === event.key || 'ArrowDown' === event.key)) {
				const roots = keyTargets(node);
				('ArrowUp' === event.key ? roots : roots.slice().reverse()).forEach(function (root) {
					moveBranch(root, 'ArrowUp' === event.key ? -1 : 1);
				});
				serialize();
				reveal(node);
				focusNode(node);
			} else if (event.altKey && ('ArrowLeft' === event.key || 'ArrowRight' === event.key)) {
				const roots = keyTargets(node);
				('ArrowRight' === event.key ? roots : roots.slice().reverse()).forEach(function (root) {
					shiftBranch(root, 'ArrowRight' === event.key ? 1 : -1);
				});
				reveal(node);
				focusNode(node);
			} else if ('ArrowUp' === event.key || 'ArrowDown' === event.key || 'Home' === event.key || 'End' === event.key) {
				const target = {
					'ArrowUp': nodes[ index - 1 ],
					'ArrowDown': nodes[ index + 1 ],
					'Home': nodes[ 0 ],
					'End': nodes[ nodes.length - 1 ],
				}[ event.key ];
				if (target && event.shiftKey) {
					anchor = anchor && nodes.includes(anchor) ? anchor : node;
					selectRange(anchor, target);
				}
				focusNode(target);
			} else if ('ArrowLeft' === event.key || 'ArrowRight' === event.key) {
				// Collapse or expand; on a collapsed or childless node, Left goes to the parent.
				const isOpen = node.classList.contains('has-children') && !node.classList.contains('is-collapsed');
				if ('ArrowRight' === event.key && node.classList.contains('is-collapsed')) {
					setCollapsed(node, false);
				} else if ('ArrowLeft' === event.key && isOpen) {
					setCollapsed(node, true);
				} else if ('ArrowLeft' === event.key) {
					focusNode(nodes.slice(0, index).reverse().find(function (item) {
						return depthOf(item) < depthOf(node);
					}));
				}
			} else if (' ' === event.key) {
				if (event.ctrlKey || event.metaKey) {
					toggleBranchSelection(node);
				} else if (event.shiftKey && anchor) {
					selectRange(anchor, node);
				} else {
					setSelected(node, !selected.has(node));
					anchor = node;
					updateBulkBar();
				}
			} else if ('Enter' === event.key) {
				node.querySelector('.js-dwp-mb-edit').click();
			} else if ('Delete' === event.key) {
				const removing = branchesOf(keyTargets(node));
				const next     = nodes.slice(index + 1).find(function (item) {
					return !removing.includes(item);
				}) || nodes.slice(0, index).reverse().find(function (item) {
					return !removing.includes(item);
				});
				if (1 === removing.length || window.confirm(t.removeSelectedConfirm.replace('%d', String(removing.length)))) {
					removeNodes(removing);
					focusNode(next);
				}
			} else if ('Escape' === event.key && selected.size) {
				clearSelection();
			} else if ('?' === event.key) {
				openShortcuts();
			} else {
				handled = false;
			}

			if (handled) {
				event.preventDefault();
			}
		});

		/* ------------------------------------------------------------------ dialogs */

		// Plain elements with the dialog role, shown and hidden by the script. A <dialog> element
		// showed on load and could not be closed on this page in Firefox (cause not found); this
		// does not depend on how a browser handles <dialog>. They hang directly under <body>, so
		// the fixed settings frame cannot clip or cover them. One overlay dims the page for all.
		const dialogOverlay = el('div', 'dwp-mb-overlay');
		let openBox         = null;
		let focusBeforeBox  = null;

		dialogOverlay.hidden = true;
		document.body.appendChild(dialogOverlay);

		function createDialog(id, titleText) {
			const dialog  = el('div', 'dwp-mb-dialog');
			const inner   = el('div', 'dwp-mb-dialog-inner');
			const header  = el('div', 'dwp-mb-dialog-header');
			const title   = el('h2', '', titleText);
			const body    = el('div', 'dwp-mb-dialog-body');
			const footer  = el('div', 'dwp-mb-dialog-footer');
			const closeX  = iconButton('dashicons-no-alt', t.close, 'js-dwp-mb-dialog-close');
			const closeOk = el('button', 'button button-primary js-dwp-mb-dialog-close', t.close);

			title.id = id;
			dialog.hidden = true;
			dialog.setAttribute('role', 'dialog');
			dialog.setAttribute('aria-modal', 'true');
			dialog.setAttribute('aria-labelledby', id);
			closeOk.type = 'button';
			header.append(title, closeX);
			footer.appendChild(closeOk);
			inner.append(header, body, footer);
			dialog.appendChild(inner);
			dialog.addEventListener('click', function (event) {
				if (event.target.closest('.js-dwp-mb-dialog-close')) {
					closeDialog();
				}
			});
			document.body.appendChild(dialog);

			return { element: dialog, body: body, footer: footer, closeX: closeX };
		}

		// Opening moves the focus to the close button (so Enter closes it too); closing returns it.
		function openDialog(box) {
			focusBeforeBox = document.activeElement;
			dialogOverlay.hidden = false;
			box.element.hidden = false;
			openBox = box;
			box.closeX.focus();
		}

		function closeDialog() {
			if (!openBox) {
				return;
			}
			dialogOverlay.hidden = true;
			openBox.element.hidden = true;
			openBox = null;
			if (focusBeforeBox && document.contains(focusBeforeBox)) {
				focusBeforeBox.focus();
			}
		}

		// Close with either button, a click on the dimmed page around it, or Esc.
		dialogOverlay.addEventListener('click', closeDialog);
		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key && openBox) {
				event.preventDefault();
				event.stopPropagation();
				closeDialog();
			}
		}, true);

		/* ------------------------------------------------------------------ shortcuts overview */

		const shortcutsBox   = createDialog('dwp-mb-shortcuts-title', t.shortcutsTitle);
		const shortcutsTable = el('table', 'widefat striped');
		t.shortcuts.forEach(function (shortcut) {
			const line = el('tr');
			line.append(el('th', '', shortcut[ 0 ]), el('td', '', shortcut[ 1 ]));
			shortcutsTable.appendChild(line);
		});
		shortcutsBox.body.append(shortcutsTable, el('p', 'description', t.shortcutsNote));

		function openShortcuts() {
			openDialog(shortcutsBox);
		}

		/* ------------------------------------------------------------------ preview */

		// The menu as the toolbar would show it, built from the editor (also before saving): on
		// admin pages or on the site, with all plugins or only those active here. Submenus open
		// and close with a click and start closed, like entering the toolbar menu.
		const previewBox    = createDialog('dwp-mb-preview-title', t.previewTitle);
		const previewTools  = el('div', 'dwp-mb-preview-tools');
		const previewSide   = el('span', 'dwp-mb-segmented');
		const previewActive = el('input');
		const previewLabel  = el('label', 'dwp-mb-preview-all', t.previewActive);
		const previewCount  = el('p', 'description dwp-mb-preview-count');
		const previewMenu   = el('div', 'dwp-mb-preview');
		const previewOpen   = new Set(); // Instance ids of the opened submenus.
		let side            = 'admin';

		[ [ 'admin', t.previewAdmin ], [ 'frontend', t.previewSite ] ].forEach(function (option) {
			const button = el('button', 'dwp-mb-segment', visibilityLabel(option[ 0 ]));
			button.type = 'button';
			button.title = option[ 1 ];
			button.dataset.side = option[ 0 ];
			previewSide.appendChild(button);
		});
		previewSide.setAttribute('role', 'group');
		previewSide.addEventListener('click', function (event) {
			const button = event.target.closest('button');
			if (button) {
				side = button.dataset.side;
				renderPreview();
			}
		});
		previewBox.element.classList.add('dwp-mb-dialog--fixed'); // Opening a submenu does not resize it.
		previewActive.type = 'checkbox';
		previewActive.addEventListener('change', renderPreview);
		previewLabel.prepend(previewActive);
		previewTools.append(previewSide, previewLabel);
		previewBox.body.append(previewTools, previewCount, previewMenu);

		function togglePreviewItem(event) {
			const line = event.target.closest('.dwp-mb-preview-item.has-children');
			if (!line || ('keydown' === event.type && 'Enter' !== event.key && ' ' !== event.key)) {
				return;
			}
			event.preventDefault();

			const instance = line.dataset.instance;
			if (previewOpen.has(instance)) {
				previewOpen.delete(instance);
			} else {
				previewOpen.add(instance);
			}
			renderPreview();
			previewMenu.querySelector('[data-instance="' + instance + '"]').focus();
		}
		previewMenu.addEventListener('click', togglePreviewItem);
		previewMenu.addEventListener('keydown', togglePreviewItem);

		// The rules of the toolbar: a node shows where its visibility allows, under a shown parent;
		// a shortcut of a plugin that is not active only when all are asked for (never one that no
		// plugin offers); a submenu without a link of its own only when something shows under it.
		function previewItems() {
			const roots = [];
			const stack = [];

			treeNodes().forEach(function (node) {
				const state  = rows.get(node);
				const source = sources.get(node) || {};
				const depth  = depthOf(node);
				const item   = { node: node, state: state, source: source, children: [] };

				stack.length = depth;
				(0 === depth ? roots : (stack[ depth - 1 ] || { children: roots }).children).push(item);
				stack[ depth ] = item;
			});

			function shown(item) {
				const visibility = item.state.visibility;
				if ('both' !== visibility && side !== visibility) {
					return false;
				}
				if ('shortcut' === item.state.type) {
					return 'active' === item.source.status || (!previewActive.checked && 'unknown' !== item.source.status);
				}
				return true;
			}

			function prune(items) {
				return items.filter(shown).map(function (item) {
					return Object.assign({}, item, { children: prune(item.children) });
				}).filter(function (item) {
					return 'shortcut' === item.state.type || '' !== item.state.href || item.children.length > 0;
				});
			}

			return prune(roots);
		}

		function renderPreview() {
			const items = previewItems();

			// Everything that shows, also inside closed submenus.
			function countItems(levelItems) {
				return levelItems.reduce(function (sum, item) {
					return sum + 1 + countItems(item.children);
				}, 0);
			}
			const count = countItems(items);

			previewSide.querySelectorAll('button').forEach(function (button) {
				button.setAttribute('aria-pressed', String(button.dataset.side === side));
			});

			function list(levelItems) {
				const ul = el('ul');
				levelItems.forEach(function (item) {
					const li    = el('li');
					const line  = el('span', 'dwp-mb-preview-item');
					const href  = 'shortcut' === item.state.type ? (item.source.href || '') : item.state.href;
					const label = el('span', 'dwp-mb-preview-label', displayTitle(item.state));
					const open  = previewOpen.has(item.node.dataset.instance);

					line.title = href;
					line.appendChild(label);
					if (item.children.length) {
						line.classList.add('has-children');
						line.dataset.instance = item.node.dataset.instance;
						line.setAttribute('aria-expanded', String(open));
						line.setAttribute('role', 'button');
						line.tabIndex = 0;
						line.appendChild(el('span', 'dwp-mb-preview-more', open ? '▾' : '▸'));
					}
					if ('shortcut' === item.state.type && 'active' !== item.source.status) {
						li.classList.add('is-inactive');
					}
					if (href) {
						line.appendChild(el('span', 'dwp-mb-preview-url', href.replace(window.location.origin, '')));
					}
					li.appendChild(line);
					if (item.children.length && open) {
						li.appendChild(list(item.children));
					}
					ul.appendChild(li);
				});
				return ul;
			}

			const root  = el('div', 'dwp-mb-preview-root');
			root.prepend(el('span', 'dashicons dashicons-admin-home'));
			root.appendChild(el('span', '', data.siteName));
			previewMenu.replaceChildren(root, items.length ? list(items) : el('p', 'dwp-mb-preview-empty', t.previewEmpty));
			previewCount.textContent = t.previewCount.replace('%d', String(count));
		}

		function openPreview() {
			previewOpen.clear();
			renderPreview();
			openDialog(previewBox);
		}

		/* ------------------------------------------------------------------ import and export */

		// A saved tree (nested, as stored) to rows for the editor, with the catalogue data of each
		// shortcut. Used for the default and for imported JSON files.
		const poolById  = new Map();
		const customRow = data.pool.find(function (row) {
			return 'submenu' === row.type;
		});
		data.pool.forEach(function (row) {
			if ('shortcut' === row.type && !poolById.has(row.id)) {
				poolById.set(row.id, row);
			}
		});

		function treeToRows(level, depth, out) {
			Object.keys(level || {}).forEach(function (instance) {
				const meta = level[ instance ];
				if (!meta || 'object' !== typeof meta) {
					return;
				}

				let row;
				if ('submenu' === meta.type) {
					row = Object.assign({}, customRow, { id: String(meta.id || 'submenu'), defaultTitle: '', label: '', href: '#' === meta.href ? '' : String(meta.href || '') });
				} else if (poolById.has(meta.id)) {
					row = Object.assign({}, poolById.get(meta.id));
				} else {
					row = { id: String(meta.id || ''), type: 'shortcut', provider: '', providerName: t.statusUnknown, status: 'unknown', kind: 'both', defaultTitle: String(meta.title || meta.id || ''), label: '', href: '' };
				}

				const title = String(meta.title || '');
				out.push(Object.assign(row, {
					instance: String(instance),
					title: 'shortcut' === row.type && title === row.defaultTitle ? '' : title,
					titleForm: String(meta[ 'title-form' ] || ''),
					visibility: [ 'admin', 'frontend' ].includes(meta.visibility) ? meta.visibility : 'both',
					depth: depth,
				}));

				if (meta.children && 'object' === typeof meta.children && depth < maxDepth) {
					treeToRows(meta.children, depth + 1, out);
				}
			});
			return out;
		}

		function replaceWith(newRows) {
			clearSelection();
			treeNodes().forEach(function (node) {
				node.remove();
			});
			newRows.forEach(function (row) {
				tree.appendChild(createTreeNode(row, row.depth));
			});
			updateEmptyState();
			serialize();
		}

		// Adds the rows the menu lacks (by instance id, so nothing twice): after their predecessor
		// from the source if the menu has it, else first under their parent, else at the end.
		function mergeRows(newRows) {
			const present  = new Map(treeNodes().map(function (node) {
				return [ node.dataset.instance, node ];
			}));
			const parents  = [];
			const previous = {};
			const lastAt   = {};
			let added      = 0;

			newRows.forEach(function (row) {
				parents.length = row.depth;
				const parent   = 0 === row.depth ? '' : (parents[ row.depth - 1 ] || '');
				previous[ row.instance ] = lastAt[ parent ] || '';
				lastAt[ parent ] = row.instance;
				parents[ row.depth ] = row.instance;
				row.parent = parent;
			});

			newRows.forEach(function (row) {
				if (present.has(row.instance)) {
					return;
				}

				const before = present.get(previous[ row.instance ]);
				const parent = present.get(row.parent);
				let after    = null;
				let depth    = 0;

				if (before) {
					const branch = branchOf(before);
					after = branch[ branch.length - 1 ];
					depth = depthOf(before);
				} else if (parent) {
					after = parent;
					depth = depthOf(parent) + 1;
				}

				const node = createTreeNode(row, Math.min(depth, maxDepth));
				tree.insertBefore(node, after ? after.nextElementSibling : null);
				present.set(row.instance, node);
				added++;
			});

			updateEmptyState();
			serialize();
			return added;
		}

		const message = el('p', 'dwp-mb-message');
		message.hidden = true;
		tree.before(message);

		function showMessage(text) {
			message.textContent = text;
			message.hidden = false;
		}

		const transferBox = createDialog('dwp-mb-transfer-title', t.transferTitle);

		function radio(name, value, text, checked) {
			const label = el('label', 'dwp-mb-choice');
			const input = el('input');
			input.type = 'radio';
			input.name = name;
			input.value = value;
			input.checked = checked;
			label.append(input, el('span', '', text));
			return label;
		}

		const exportButton = el('button', 'button', t.exportButton);
		exportButton.type = 'button';
		exportButton.addEventListener('click', function () {
			const payload = { format: 'core-functionality/toolbar-menu', version: 1, tree: JSON.parse(serialized.value || '{}') };
			const url     = URL.createObjectURL(new Blob([ JSON.stringify(payload, null, 2) ], { type: 'application/json' }));
			const link    = el('a');
			link.href = url;
			link.download = 'toolbar-menu.json';
			document.body.appendChild(link);
			link.click();
			link.remove();
			window.setTimeout(function () {
				URL.revokeObjectURL(url);
			}, 1000);
		});

		const hasDefault   = !!data.default && 'object' === typeof data.default;
		const sourceDef    = radio('dwp-mb-import-source', 'default', t.importDefault, hasDefault);
		const sourceFile   = radio('dwp-mb-import-source', 'file', t.importFile, !hasDefault);
		const fileInput    = el('input', 'dwp-mb-import-file');
		const modeReplace  = radio('dwp-mb-import-mode', 'replace', t.importReplace, true);
		const modeMerge    = radio('dwp-mb-import-mode', 'merge', t.importMerge, false);
		const importButton = el('button', 'button button-primary', t.importButton);
		const importError  = el('p', 'dwp-mb-import-error');

		fileInput.type = 'file';
		fileInput.accept = '.json,application/json';
		fileInput.addEventListener('change', function () {
			sourceFile.querySelector('input').checked = true;
		});
		if (!hasDefault) {
			sourceDef.querySelector('input').disabled = true;
			sourceDef.appendChild(el('span', 'description', ' ' + t.importNoDefault));
		}
		importButton.type = 'button';
		importError.hidden = true;

		function fieldset(legendText, children) {
			const set = el('fieldset', 'dwp-mb-fieldset');
			set.appendChild(el('legend', '', legendText));
			children.forEach(function (child) {
				set.appendChild(child);
			});
			return set;
		}

		// Export and import each on their own tab: only what applies is shown.
		const exportPanel = el('div', 'dwp-mb-transfer-panel');
		const importPanel = el('div', 'dwp-mb-transfer-panel');
		const transferTabs = el('span', 'dwp-mb-segmented dwp-mb-transfer-tabs');

		exportPanel.append(el('p', '', t.exportText), exportButton);
		importPanel.append(
			fieldset(t.importSource, [ sourceDef, sourceFile, fileInput ]),
			fieldset(t.importMode, [ modeReplace, modeMerge ]),
			importError,
			importButton
		);

		function showTransferTab(panel) {
			exportPanel.hidden = panel !== exportPanel;
			importPanel.hidden = panel !== importPanel;
			transferTabs.querySelectorAll('button').forEach(function (button, index) {
				button.setAttribute('aria-pressed', String(panel === [ exportPanel, importPanel ][ index ]));
			});
		}

		[ [ t.exportHeading, exportPanel ], [ t.importHeading, importPanel ] ].forEach(function (tab) {
			const button = el('button', 'dwp-mb-segment', tab[ 0 ]);
			button.type = 'button';
			button.addEventListener('click', function () {
				showTransferTab(tab[ 1 ]);
			});
			transferTabs.appendChild(button);
		});
		transferTabs.setAttribute('role', 'group');
		showTransferTab(exportPanel);

		// Both panels share one place, so the dialog takes the size of the larger and keeps it.
		const transferPanels = el('div', 'dwp-mb-transfer-panels');
		transferPanels.append(exportPanel, importPanel);
		transferBox.body.append(transferTabs, transferPanels);

		function failImport(text) {
			importError.textContent = text;
			importError.hidden = false;
		}

		function applyImport(nested) {
			if (!nested || 'object' !== typeof nested || Array.isArray(nested)) {
				failImport(t.importInvalid);
				return;
			}

			const newRows = treeToRows(nested, 0, []);
			const merge   = modeMerge.querySelector('input').checked;

			if (merge) {
				showMessage(t.importMerged.replace('%d', String(mergeRows(newRows))));
			} else {
				replaceWith(newRows);
				showMessage(t.importReplaced.replace('%d', String(newRows.length)));
			}
			closeDialog();
		}

		importButton.addEventListener('click', function () {
			importError.hidden = true;

			if (sourceDef.querySelector('input').checked) {
				applyImport(data.default);
				return;
			}

			const file = fileInput.files && fileInput.files[ 0 ];
			if (!file) {
				failImport(t.importNoFile);
				return;
			}

			file.text().then(function (text) {
				let parsed = null;
				try {
					parsed = JSON.parse(text);
				} catch (error) {
					failImport(t.importInvalid);
					return;
				}
				applyImport(parsed && parsed.tree ? parsed.tree : parsed);
			});
		});

		const transferButton = document.querySelector('.js-dwp-mb-transfer');
		if (transferButton) {
			transferButton.addEventListener('click', function () {
				importError.hidden = true;
				openDialog(transferBox);
			});
		}

		/* ------------------------------------------------------------------ tools above the menu */

		function toolButton(icon, text) {
			const button = el('button', 'button button-small dwp-mb-tool', text);
			button.type = 'button';
			button.prepend(el('span', 'dashicons ' + icon));
			return button;
		}

		// The explanations above the pool and the menu are collapsed by default and open together,
		// with the button above either column; the browser remembers when they are open.
		const intros       = root.querySelectorAll('.js-dwp-mb-intro');
		const introButton  = toolButton('dashicons-info-outline', t.explanation);
		const introButtons = [ introButton, toolButton('dashicons-info-outline', t.explanation) ];
		let introOpen      = false;
		try {
			introOpen = '1' === window.localStorage.getItem('dwp-mb-intro-open');
		} catch (error) {
			// Storage unavailable: the explanation starts collapsed.
		}

		function showIntro(open) {
			introOpen = open;
			intros.forEach(function (intro) {
				intro.hidden = !open;
			});
			introButtons.forEach(function (button) {
				button.setAttribute('aria-expanded', String(open));
				button.classList.toggle('is-open', open);
			});
			try {
				window.localStorage.setItem('dwp-mb-intro-open', open ? '1' : '0');
			} catch (error) {
				// Storage unavailable: the state is simply not remembered.
			}
		}

		introButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				showIntro(!introOpen);
			});
		});
		showIntro(introOpen);

		const poolTools = el('span', 'dwp-mb-tools');
		poolTools.appendChild(introButtons[ 1 ]);
		pool.closest('.dwp-mb-column').querySelector('h3').appendChild(poolTools);

		const branchesButton = toolButton('dashicons-editor-expand', t.expandAll);
		branchesButton.addEventListener('click', toggleAllBranches);

		function updateBranchesButton() {
			const parents = tree.querySelectorAll('.js-dwp-mb-tree-node.has-children');
			const anyShut = tree.querySelector('.js-dwp-mb-tree-node.is-collapsed');
			branchesButton.hidden = 0 === parents.length;
			branchesButton.lastChild.textContent = anyShut ? t.expandAll : t.collapseAll;
			branchesButton.firstChild.className = 'dashicons ' + (anyShut ? 'dashicons-editor-expand' : 'dashicons-editor-contract');
		}

		const previewButton = toolButton('dashicons-visibility', t.preview);
		previewButton.addEventListener('click', openPreview);

		const helpButton = toolButton('dashicons-editor-help', t.shortcutsButton);
		helpButton.addEventListener('click', openShortcuts);

		const treeTools = el('span', 'dwp-mb-tools');
		treeTools.append(introButton, branchesButton, previewButton, helpButton);
		tree.closest('.dwp-mb-column').querySelector('h3').appendChild(treeTools);

		/* ------------------------------------------------------------------ serialize */

		// The group a custom submenu stands for: the group all its shortcuts share (as in MenuBuilder.php),
		// so they get their short titles under it. Mixed or none: no group.
		function submenuGroups(nodes) {
			const result = new Map();
			nodes.forEach(function (node, index) {
				if ('submenu' !== rows.get(node).type) {
					return;
				}
				const depth  = depthOf(node);
				const groups = new Set();
				for (let i = index + 1; i < nodes.length && depthOf(nodes[ i ]) > depth; i++) {
					const child = rows.get(nodes[ i ]);
					if (depthOf(nodes[ i ]) === depth + 1 && 'shortcut' === child.type && child.group) {
						groups.add(child.group);
					}
				}
				result.set(node, 1 === groups.size ? [ ...groups ][ 0 ] : '');
			});
			return result;
		}

		// Writes the tree as nested JSON. A node can be at most one level deeper than the one before it.
		// Also refreshes what each node inherits from its parents.
		function serialize() {
			const result       = {};
			const stack        = []; // stack[ depth ] = children object of the last node at that depth.
			const restrictions = []; // restrictions[ depth ] = visibility restriction ('' for none) of that node.
			const groups       = []; // groups[ depth ] = group of that node (a custom submenu: that of its shortcuts).
			const nodes        = treeNodes();
			const submenus     = submenuGroups(nodes);
			let previous       = -1;

			nodes.forEach(function (node) {
				const state = rows.get(node);
				const depth = Math.min(depthOf(node), previous + 1, maxDepth);
				if (depth !== depthOf(node)) {
					setDepth(node, depth);
				}

				const inherited = 0 === depth ? '' : restrictions[ depth - 1 ];
				refreshVisibility(node, inherited);
				restrictions[ depth ] = inherited || ('both' === state.visibility ? '' : state.visibility);

				// A move can change the parent, and with it the short or full title.
				const parentGroup = 0 === depth ? '' : (groups[ depth - 1 ] || '');
				groups[ depth ]   = submenus.has(node) ? submenus.get(node) : state.group;
				if (parentGroup !== state.parentGroup) {
					state.parentGroup = parentGroup;
					refreshHeader(node);
				}

				const payload = {
					id: state.id,
					type: state.type,
					title: state.title,
				};
				if (state.titleForm) {
					payload[ 'title-form' ] = state.titleForm;
				}
				Object.assign(payload, {
					href: 'submenu' === state.type ? state.href : '',
					visibility: state.visibility,
					children: {},
				});

				const parent = 0 === depth ? result : stack[ depth - 1 ];
				parent[ node.dataset.instance ] = payload;
				stack[ depth ] = payload.children;
				stack.length = depth + 1;
				previous = depth;
			});

			serialized.value = JSON.stringify(result);
			refreshTreeState();
			recordHistory(serialized.value);
		}

		/* ------------------------------------------------------------------ undo and redo */

		// Every change to the menu goes through serialize(), which records the state before it.
		// Typing in one field counts as one step.
		const undoStack     = [];
		const redoStack     = [];
		const historyLimit  = 50;
		let currentJson     = null;
		let currentSnapshot = null;
		let restoring       = false;
		let typingIn        = null; // The node whose title or URL is being typed in, set by its input.
		let lastTyped       = { node: null, at: 0 };

		function snapshot() {
			return treeNodes().map(function (node) {
				const state = rows.get(node);
				return Object.assign({}, sources.get(node), {
					title: state.title,
					titleForm: state.titleForm,
					href: state.href,
					visibility: state.visibility,
					instance: node.dataset.instance,
					depth: depthOf(node),
				});
			});
		}

		function recordHistory(json) {
			const now      = Date.now();
			const typing   = typingIn;
			const sameEdit = null !== typing && typing === lastTyped.node && now - lastTyped.at < 2000;
			typingIn       = null;
			lastTyped      = { node: typing, at: now };

			if (!restoring && null !== currentJson && json !== currentJson) {
				if (!sameEdit) {
					undoStack.push(currentSnapshot);
					if (undoStack.length > historyLimit) {
						undoStack.shift();
					}
				}
				redoStack.length = 0;
			}

			currentJson     = json;
			currentSnapshot = snapshot();
			updateHistoryButtons();
		}

		function restore(state) {
			restoring = true;
			clearSelection();
			treeNodes().forEach(function (node) {
				node.remove();
			});
			state.forEach(function (row) {
				tree.appendChild(createTreeNode(row, row.depth));
			});
			updateEmptyState();
			serialize();
			restoring = false;
		}

		function undo() {
			if (undoStack.length) {
				redoStack.push(currentSnapshot);
				restore(undoStack.pop());
			}
		}

		function redo() {
			if (redoStack.length) {
				undoStack.push(currentSnapshot);
				restore(redoStack.pop());
			}
		}

		function updateHistoryButtons() {
			undoButton.disabled = 0 === undoStack.length;
			redoButton.disabled = 0 === redoStack.length;
		}

		const undoButton = iconButton('dashicons-undo', t.undo, 'dwp-mb-history');
		const redoButton = iconButton('dashicons-redo', t.redo, 'dwp-mb-history');
		undoButton.addEventListener('click', undo);
		redoButton.addEventListener('click', redo);
		treeTools.prepend(undoButton, redoButton);

		// Ctrl+Z / Ctrl+Y (or Ctrl+Shift+Z), except while typing in a field, which has its own undo.
		document.addEventListener('keydown', function (event) {
			const target = event.target instanceof Element ? event.target : null;
			if (!(event.ctrlKey || event.metaKey) || event.altKey || (target && target.closest('input, textarea, select, [contenteditable]'))) {
				return;
			}
			const key = event.key.toLowerCase();
			if ('z' === key && !event.shiftKey) {
				event.preventDefault();
				undo();
			} else if ('y' === key || ('z' === key && event.shiftKey)) {
				event.preventDefault();
				redo();
			}
		});

		/* ------------------------------------------------------------------ start */

		buildPool();
		filterPool();

		data.tree.forEach(function (row) {
			tree.appendChild(createTreeNode(row, row.depth));
		});
		updateEmptyState();
		serialize();

		if (form) {
			form.addEventListener('submit', serialize);
		}
	});
}());
