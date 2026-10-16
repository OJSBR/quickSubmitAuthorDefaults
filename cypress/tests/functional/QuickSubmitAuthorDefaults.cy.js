/**
 * @file cypress/tests/functional/QuickSubmitAuthorDefaults.cy.js
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Functional tests: the settings form of the plugin, the form of a new
 * contributor of Quick Submit opening with the values of the journal, each of
 * them switched off, and a contributor saved from that form carrying them.
 *
 * Quick Submit draws its contributors with the legacy contributor grid of the
 * core (grid.users.author.AuthorGridHandler), and the grid is part of every
 * OJS 3.5, Quick Submit installed or not. The spec opens and saves the form of
 * that grid exactly as the Quick Submit page does, so it also runs in PKP's
 * continuous integration, which does not ship Quick Submit.
 *
 * Parameters (--env): contextPath, adminUser, adminPassword (captcha on login
 * must be off for the run). The defaults match the data set of PKP's continuous
 * integration. The settings of the journal are put back as they were found and
 * the contributor created here is deleted. Assertions use names, ids and API
 * data, never labels.
 */

describe('Quick Submit contributor defaults plugin', function() {
	const contextPath = Cypress.env('contextPath') || 'publicknowledge';
	const adminUser = Cypress.env('adminUser') || 'admin';
	const adminPassword = Cypress.env('adminPassword') || 'admin';

	const CHOSEN_EMAIL = 'authors.' + Date.now() + '@example.invalid';

	// ---- OJSBR spec helpers (padrão v2): work on OJS/OMP 3.5 and in PKP's CI ----

	const pageUrl = (path) => '/index.php/' + contextPath + (path ? '/' + path : '');

	// Same as PKP's cy.waitJQuery(). The Plugins tab can keep requests open for a
	// while (the plugin gallery), hence the timeout.
	const waitJQuery = () => cy.window({timeout: 60000}).should((win) => {
		expect(win.jQuery && win.jQuery.active, 'pending jQuery requests').to.eq(0);
	});

	// Requests carry the browser's User-Agent, so that the session is not dropped.
	const request = (options) => cy.window({log: false}).then((win) => cy.request(Object.assign(
		typeof options === 'string' ? {url: options} : options,
		{headers: Object.assign({'User-Agent': win.navigator.userAgent}, (typeof options === 'string' ? {} : options.headers) || {})}
	)));

	// Signs in through requests (the login page can re-render while it is typed into).
	const login = (username, password) => {
		cy.clearCookies();
		request(pageUrl('login')).then((response) => {
			const token = /name="csrfToken" value="([^"]+)"/.exec(response.body)[1];
			// The form posts to the URL with the language: a redirect would turn the POST into a GET.
			const action = /<form[^>]*id="login"[^>]*action="([^"]+)"/.exec(response.body)[1];
			request({method: 'POST', url: action, form: true, body: {csrfToken: token, username: username, password: password}, log: false});
		});
		cy.visit(pageUrl('submissions') + '?reload=' + Date.now());
		cy.get('form#login').should('not.exist');
	};

	// REST API calls made from the page itself, so they carry the browser's own session.
	const api = (path, options = {}) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(path, Object.assign({credentials: 'same-origin'}, options)).then((response) => {
			if (!response.ok) {
				return response.text().then((text) => {
					throw new Error(path + ' answered ' + response.status + ': ' + text.slice(0, 300));
				});
			}
			return response.json();
		}),
		{log: false, timeout: 30000}
	));

	const withToken = (method, body) => cy.window({log: false}).then((win) => ({
		method,
		headers: {'Content-Type': 'application/json', 'X-Csrf-Token': win.pkp.currentUser.csrfToken},
		body: body ? JSON.stringify(body) : undefined,
	}));

	// A request whose error answer is kept, not thrown: yields {status, body}.
	const send = (path, method, body) => withToken(method, body).then((options) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(path, Object.assign({credentials: 'same-origin'}, options)).then((response) => response.json().then((json) => ({status: response.status, body: json}))),
		{log: false, timeout: 30000}
	)));

	// The website settings page on its Plugins tab. Loaded once per run: loading
	// it again while its plugin gallery request is pending stalls the web server
	// of PKP's CI; API calls and settings modals work on the page already open.
	const openPluginsTab = () => {
		cy.visit(pageUrl('management/settings/website') + '?reload=' + Date.now() + '#plugins');
		cy.get('button[id="plugins-button"]', {timeout: 60000}).click();
		cy.get('button[id="plugins-button"]').should('have.attr', 'aria-selected', 'true');
		waitJQuery();
	};

	// Enables the plugin in the grid when it is off (never turns it off).
	const enablePlugin = (rowName) => {
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]', {timeout: 30000}).then(($checkbox) => {
			if (!$checkbox.is(':checked')) {
				cy.wrap($checkbox).click();
				waitJQuery();
			}
		});
		cy.get('input[id^="select-cell-' + rowName + '-enabled"]').should('be.checked');
	};

	// Opens the settings modal from the grid, without reloading the page, and
	// waits for the form handler: after a failed validation the modal replaces
	// the form, and a click before that sends the form natively.
	const openPluginSettings = (rowName, formSelector) => {
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]', {timeout: 30000}).then(($link) => {
			if (!$link.is(':visible')) {
				cy.get('tr[id$="-row-' + rowName + '"] a.show_extras').first().click();
			}
		});
		cy.get('a[id*="-row-' + rowName + '-settings-button-"]').first().click({force: true});
		waitJQuery();
		cy.window().should((win) => {
			expect(win.jQuery(formSelector).data('pkp.handler')).to.exist;
		});
	};

	// ---- the settings of this plugin ----

	const FLAGS = ['fillEmail', 'fillCountry', 'fillUserGroup'];
	const settingsForm = 'form[id="quickSubmitAuthorDefaultsSettingsForm"]';
	// The URL the settings form posts to, read from the form itself, and what the
	// journal had before this run.
	let settingsAction = null;
	let settingsWere = null;

	// Saves the settings from whatever page is open, exactly as the modal does.
	const saveSettings = (values) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(settingsAction, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
			body: FLAGS.filter((name) => values[name])
				.map((name) => name + '=on')
				.concat('email=' + encodeURIComponent(values.email || ''))
				.concat('userGroupId=' + encodeURIComponent(values.userGroupId || ''))
				.concat('csrfToken=' + encodeURIComponent(win.pkp.currentUser.csrfToken))
				.join('&'),
		}).then((response) => {
			if (!response.ok) {
				throw new Error('the settings answered ' + response.status);
			}
			return response.json();
		}).then((json) => {
			// A saved form answers with no content; a refused one comes back as the
			// form again, with its errors.
			if (json.content) {
				throw new Error('the settings were refused: ' + Cypress.$('<div>').html(json.content).text().replace(/\s+/g, ' ').slice(0, 300));
			}
			return json;
		}),
		{log: false, timeout: 30000}
	));

	// The settings form as the server renders it now, without loading a page. The
	// action carries `save=1`, which has to go for the form to come back unsaved.
	const fetchSettings = () => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(settingsAction.replace(/([?&])save=[^&]*&?/, '$1').replace(/[?&]$/, ''), {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
			.then((response) => response.json())
			.then((json) => Cypress.$('<div>').html(json.content || '')),
		{log: false, timeout: 30000}
	));

	const settingsOf = ($form) => ({
		fillEmail: $form.find('input[name="fillEmail"]').is(':checked'),
		fillCountry: $form.find('input[name="fillCountry"]').is(':checked'),
		fillUserGroup: $form.find('input[name="fillUserGroup"]').is(':checked'),
		email: $form.find('input[name="email"]').val() || '',
		userGroupId: $form.find('select[name="userGroupId"]').val() || '',
	});

	// ---- the contributor form of Quick Submit ----

	// A submission the contributor grid accepts: one of the journal, with a
	// publication that can still be edited.
	let work = null;
	const workSubmission = () => {
		if (work) {
			return cy.wrap(work, {log: false});
		}
		return api(pageUrl('api/v1/submissions?status=1&count=20')).then((submissions) => {
			const found = (submissions.items || []).find((item) => item.currentPublicationId);
			expect(found, 'a queued submission of the journal').to.not.eq(undefined);
			work = {id: found.id, publicationId: found.currentPublicationId, locale: found.locale};
			return cy.wrap(work, {log: false});
		});
	};

	const gridUrl = (op, submission) => pageUrl('$$$call$$$/grid/users/author/author-grid/' + op)
		+ '?submissionId=' + submission.id + '&publicationId=' + submission.publicationId;

	// The form the "Add contributor" link of Quick Submit opens, parsed.
	const newContributorForm = () => workSubmission().then((submission) => cy.window({log: false}).then((win) => cy.wrap(
		win.fetch(gridUrl('add-author', submission), {credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest'}})
			.then((response) => response.json())
			.then((json) => {
				expect(json.status, 'the grid opens the form').to.eq(true);
				return Cypress.$('<div>').html(json.content);
			}),
		{log: false, timeout: 30000}
	)));

	const valuesOf = ($form) => ({
		email: $form.find('input[name="email"]').val() || '',
		country: $form.find('select[name="country"]').val() || '',
		userGroupId: $form.find('input[name="userGroupId"]:checked').val() || '',
		userGroupIds: $form.find('input[name="userGroupId"]').map((i, el) => el.value).get(),
	});

	// What the journal has, read from the API.
	let journal = null;
	const journalData = () => (journal ? cy.wrap(journal, {log: false}) : api(pageUrl('api/v1/contexts?count=100')).then((contexts) => {
		const found = (contexts.items || []).find((item) => item.urlPath === contextPath);
		expect(found, 'the journal ' + contextPath).to.not.eq(undefined);
		return api(pageUrl('api/v1/contexts/' + found.id)).then((context) => {
			journal = {contactEmail: context.contactEmail || '', country: context.country || ''};
			return journal;
		});
	}));

	it('Enables the plugin and offers a settings form for the journal', function() {
		login(adminUser, adminPassword);
		openPluginsTab();
		enablePlugin('quicksubmitauthordefaultsplugin');
		openPluginSettings('quicksubmitauthordefaultsplugin', settingsForm);

		FLAGS.forEach((name) => cy.get(settingsForm + ' input[name="' + name + '"]').should('have.length', 1));
		cy.get(settingsForm + ' input[name="email"]').should('have.length', 1);
		cy.get(settingsForm + ' select[name="userGroupId"] option').should('have.length.greaterThan', 1);
		cy.get(settingsForm).invoke('text').should('not.contain', '##');
		cy.get(settingsForm).invoke('attr', 'action').then((action) => {
			settingsAction = action;
		});
		cy.get(settingsForm).then(($form) => {
			settingsWere = settingsOf($form);
		});
	});

	it('Opens a new contributor with the email, the country and the Author role of the journal', function() {
		login(adminUser, adminPassword);
		cy.then(() => saveSettings({fillEmail: true, fillCountry: true, fillUserGroup: true}));
		fetchSettings().then(($form) => {
			expect(settingsOf($form), 'saved through the form itself').to.deep.eq({fillEmail: true, fillCountry: true, fillUserGroup: true, email: '', userGroupId: ''});
		});

		journalData().then((context) => newContributorForm().then(($form) => {
			const values = valuesOf($form);
			expect(context.contactEmail, 'the journal has a contact address').to.not.eq('');
			expect(values.email, 'the contact address of the journal').to.eq(context.contactEmail);
			expect(values.country, 'the country of the journal').to.eq(context.country);
			expect(values.userGroupId, 'a role of contributors is selected').to.not.eq('');
			expect(values.userGroupIds, 'among the roles the form offers').to.include(values.userGroupId);
			// Still a form to fill in: the name is left to the editor.
			expect($form.find('input[name^="givenName"]').val() || '').to.eq('');
		}));
	});

	it('Uses the address and the role the journal chose', function() {
		login(adminUser, adminPassword);
		newContributorForm().then(($before) => {
			const roles = valuesOf($before).userGroupIds;
			const other = roles.find((id) => id !== valuesOf($before).userGroupId) || roles[0];
			cy.then(() => saveSettings({fillEmail: true, fillCountry: true, fillUserGroup: true, email: CHOSEN_EMAIL, userGroupId: other}));
			fetchSettings().then(($settings) => {
				expect(settingsOf($settings).email).to.eq(CHOSEN_EMAIL);
				expect(settingsOf($settings).userGroupId).to.eq(other);
			});
			newContributorForm().then(($form) => {
				expect(valuesOf($form).email).to.eq(CHOSEN_EMAIL);
				expect(valuesOf($form).userGroupId).to.eq(other);
			});
		});
	});

	it('Refuses an address that is not one', function() {
		login(adminUser, adminPassword);
		cy.window({log: false}).then((win) => cy.wrap(
			win.fetch(settingsAction, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
				body: 'fillEmail=on&email=not-an-address&csrfToken=' + encodeURIComponent(win.pkp.currentUser.csrfToken),
			}).then((response) => response.json()),
			{log: false, timeout: 30000}
		)).then((json) => {
			expect(json.content || '', 'the form comes back').to.not.eq('');
			expect(Cypress.$('<div>').html(json.content).find('input[name="email"]').hasClass('error'), 'with the address marked as wrong').to.eq(true);
		});
		fetchSettings().then(($settings) => {
			expect(settingsOf($settings).email, 'nothing was stored').to.eq(CHOSEN_EMAIL);
		});
	});

	it('Leaves every field empty once switched off', function() {
		login(adminUser, adminPassword);
		cy.then(() => saveSettings({fillEmail: false, fillCountry: false, fillUserGroup: false, email: CHOSEN_EMAIL}));
		newContributorForm().then(($form) => {
			// The core form on its own: what any OJS shows without the plugin.
			expect(valuesOf($form).email).to.eq('');
			expect(valuesOf($form).country).to.eq('');
			expect(valuesOf($form).userGroupId).to.eq('');
		});
	});

	it('Saves a contributor typed with only the name, carrying the values of the journal', function() {
		login(adminUser, adminPassword);
		cy.then(() => saveSettings({fillEmail: true, fillCountry: true, fillUserGroup: true}));
		const givenName = 'Quick' + Date.now();

		workSubmission().then((submission) => newContributorForm().then(($form) => {
			const values = valuesOf($form);
			const action = $form.find('form#editAuthor').attr('action');
			// Everything the form opened with, plus the name: what the editor sends
			// when typing the name and clicking Save.
			const fields = $form.find('form#editAuthor').serializeArray().filter((field) => field.name !== 'csrfToken');
			fields.forEach((field) => {
				if (field.name === 'givenName[' + submission.locale + ']') {
					field.value = givenName;
				}
			});
			cy.window({log: false}).then((win) => cy.wrap(
				win.fetch(action, {
					method: 'POST',
					credentials: 'same-origin',
					headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest'},
					body: fields.map((field) => encodeURIComponent(field.name) + '=' + encodeURIComponent(field.value))
						.concat('csrfToken=' + encodeURIComponent(win.pkp.currentUser.csrfToken))
						.join('&'),
				}).then((response) => response.json()),
				{log: false, timeout: 30000}
			)).then((json) => {
				expect(json.status, 'the contributor was saved').to.eq(true);
			});

			// Read back from the API: the stored contributor, not the form.
			api(pageUrl('api/v1/submissions/' + submission.id + '/publications/' + submission.publicationId)).then((publication) => {
				const saved = publication.authors.find((author) => author.givenName[submission.locale] === givenName);
				expect(saved, 'the contributor is stored').to.not.eq(undefined);
				expect(saved.email).to.eq(values.email);
				expect(saved.country).to.eq(values.country);
				expect(String(saved.userGroupId)).to.eq(values.userGroupId);
				send(pageUrl('api/v1/submissions/' + submission.id + '/publications/' + submission.publicationId + '/contributors/' + saved.id), 'DELETE');
			});
		}));
	});

	after(function() {
		if (!settingsWere) {
			return;
		}
		login(adminUser, adminPassword);
		cy.then(() => saveSettings(settingsWere));
	});
});
