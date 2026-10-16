<?php

/**
 * @file plugins/generic/quickSubmitAuthorDefaults/QuickSubmitAuthorDefaultsSettingsForm.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QuickSubmitAuthorDefaultsSettingsForm
 *
 * @brief Which fields of a new contributor the journal fills, and with what.
 */

namespace APP\plugins\generic\quickSubmitAuthorDefaults;

use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorEmail;
use PKP\form\validation\FormValidatorPost;

class QuickSubmitAuthorDefaultsSettingsForm extends Form
{
    public function __construct(public QuickSubmitAuthorDefaultsPlugin $plugin, public Context $context)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorEmail($this, 'email', 'optional', 'plugins.generic.quickSubmitAuthorDefaults.settings.email.invalid'));
        $this->addCheck(new FormValidatorCustom(
            $this,
            'userGroupId',
            'optional',
            'plugins.generic.quickSubmitAuthorDefaults.settings.userGroup.invalid',
            fn ($userGroupId) => isset($this->plugin->getAuthorUserGroups((int) $this->context->getId())[(int) $userGroupId])
        ));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Load the settings of the journal.
     */
    public function initData(): void
    {
        $contextId = (int) $this->context->getId();
        foreach (QuickSubmitAuthorDefaultsPlugin::FLAGS as $name) {
            $this->setData($name, $this->plugin->getFlag($contextId, $name));
        }
        $this->setData('email', $this->plugin->getChosenEmail($contextId));
        $this->setData('userGroupId', $this->plugin->getChosenUserGroupId($contextId) ?: null);

        parent::initData();
    }

    /**
     * Read the settings sent by the form.
     */
    public function readInputData(): void
    {
        $this->readUserVars(array_keys(QuickSubmitAuthorDefaultsPlugin::DEFAULTS));
        $this->setData('email', trim((string) $this->getData('email')));

        parent::readInputData();
    }

    /**
     * Show the form, with what the journal would fill if nothing were chosen.
     *
     * @param \PKP\core\PKPRequest $request
     * @param null|string $template
     * @param bool $display
     */
    public function fetch($request, $template = null, $display = false)
    {
        $contextId = (int) $this->context->getId();
        $userGroups = $this->plugin->getAuthorUserGroups($contextId);

        $userGroupOptions = ['' => __('plugins.generic.quickSubmitAuthorDefaults.settings.userGroup.journalDefault')];
        foreach ($userGroups as $id => $userGroup) {
            $userGroupOptions[$id] = $userGroup->getLocalizedData('name');
        }

        $country = (string) $this->context->getData('country');
        $countryName = $country !== '' ? Locale::getCountries()->getByAlpha2($country)?->getLocalName() : null;

        TemplateManager::getManager($request)->assign([
            'pluginName' => $this->plugin->getName(),
            'supportEmail' => $this->plugin->getJournalEmail($this->context),
            'journalCountry' => $countryName ?: $country,
            'userGroupOptions' => $userGroupOptions,
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save the settings of the journal.
     */
    public function execute(...$functionArgs)
    {
        $contextId = (int) $this->context->getId();
        foreach (QuickSubmitAuthorDefaultsPlugin::FLAGS as $name) {
            $this->plugin->updateSetting($contextId, $name, $this->getData($name) ? 1 : 0, 'bool');
        }
        $this->plugin->updateSetting($contextId, 'email', (string) $this->getData('email'), 'string');
        $this->plugin->updateSetting($contextId, 'userGroupId', (int) $this->getData('userGroupId'), 'int');

        return parent::execute(...$functionArgs);
    }
}
