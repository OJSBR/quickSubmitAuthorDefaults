{**
 * plugins/generic/quickSubmitAuthorDefaults/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Which fields of a new contributor the journal fills, and with what.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#quickSubmitAuthorDefaultsSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form
	class="pkp_form"
	id="quickSubmitAuthorDefaultsSettingsForm"
	method="POST"
	action="{url router=\PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}"
>
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="quickSubmitAuthorDefaultsSettingsNotification"}

	<p class="pkp_help">{translate key="plugins.generic.quickSubmitAuthorDefaults.settings.intro"}</p>

	{fbvFormArea id="quickSubmitAuthorDefaultsEmail" title="plugins.generic.quickSubmitAuthorDefaults.settings.area.email"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="fillEmail" name="fillEmail" checked=$fillEmail label="plugins.generic.quickSubmitAuthorDefaults.settings.fillEmail"}
		{/fbvFormSection}
		{fbvFormSection description="plugins.generic.quickSubmitAuthorDefaults.settings.email.description"}
			{fbvElement type="email" id="email" name="email" value=$email maxlength="90" size=$fbvStyles.size.MEDIUM label="plugins.generic.quickSubmitAuthorDefaults.settings.email"}
		{/fbvFormSection}
		<p class="pkp_help">{translate key="plugins.generic.quickSubmitAuthorDefaults.settings.contactEmail" contactEmail=$contactEmail|default:"—"|escape}</p>
	{/fbvFormArea}

	{fbvFormArea id="quickSubmitAuthorDefaultsCountry" title="plugins.generic.quickSubmitAuthorDefaults.settings.area.country"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="fillCountry" name="fillCountry" checked=$fillCountry label="plugins.generic.quickSubmitAuthorDefaults.settings.fillCountry"}
		{/fbvFormSection}
		<p class="pkp_help">{translate key="plugins.generic.quickSubmitAuthorDefaults.settings.journalCountry" journalCountry=$journalCountry|default:"—"|escape}</p>
	{/fbvFormArea}

	{fbvFormArea id="quickSubmitAuthorDefaultsUserGroup" title="plugins.generic.quickSubmitAuthorDefaults.settings.area.userGroup"}
		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="fillUserGroup" name="fillUserGroup" checked=$fillUserGroup label="plugins.generic.quickSubmitAuthorDefaults.settings.fillUserGroup"}
		{/fbvFormSection}
		{fbvFormSection description="plugins.generic.quickSubmitAuthorDefaults.settings.userGroup.description"}
			{fbvElement type="select" id="userGroupId" name="userGroupId" from=$userGroupOptions selected=$userGroupId translate=false size=$fbvStyles.size.MEDIUM label="plugins.generic.quickSubmitAuthorDefaults.settings.userGroup"}
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
