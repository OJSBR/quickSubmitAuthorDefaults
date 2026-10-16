<?php

/**
 * @file plugins/generic/quickSubmitAuthorDefaults/QuickSubmitAuthorDefaultsPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QuickSubmitAuthorDefaultsPlugin
 *
 * @brief Opens the form of a new contributor of the Quick Submit plugin already
 *        filled with the email address and the country of the journal and with
 *        the Author role, so that the editor only types the name.
 *
 * In OJS 3.5 the contributors of a submission are added through the Vue form of
 * the workflow, which this plugin leaves alone. The legacy contributor form —
 * `AuthorForm`, through `grid.users.author.AuthorGridHandler` — is still the one
 * the Quick Submit plugin opens, and it requires an email address, a country and
 * a role for every contributor. An editor who is recording an article that was
 * already published elsewhere seldom has the email address of each coauthor and
 * has to invent one; this plugin puts the address of the journal there instead.
 *
 * It is done through one hook, `authorform::initdata`, fired by Form::initData()
 * with the name of the class in lowercase. Only a new contributor is touched:
 * the form of an existing one shows what was stored. The values are only what
 * the form opens with — the editor can change any of them before saving, and
 * the form validates them as it always did.
 */

namespace APP\plugins\generic\quickSubmitAuthorDefaults;

use APP\core\Application;
use APP\notification\NotificationManager;
use PKP\context\Context;
use PKP\controllers\grid\users\author\form\PKPAuthorForm;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class QuickSubmitAuthorDefaultsPlugin extends GenericPlugin
{
    /**
     * The settings of the journal and what they are while nothing was saved:
     * everything the editor asked for is on, and the email address and the
     * role are taken from the journal.
     */
    public const DEFAULTS = [
        'fillEmail' => true,
        'email' => '',
        'fillCountry' => true,
        'fillUserGroup' => true,
        'userGroupId' => 0,
    ];

    /** The settings that are a simple on/off switch. */
    public const FLAGS = ['fillEmail', 'fillCountry', 'fillUserGroup'];

    /**
     * Register the plugin and, where it is enabled, its hook.
     *
     * The form is only ever opened from a page of the journal, so the hook is
     * only added where the plugin is enabled for that journal.
     *
     * @param string $category
     * @param string $path
     * @param null|int $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!$success || Application::isUnderMaintenance() || !$this->getEnabled($mainContextId)) {
            return $success;
        }

        Hook::add('authorform::initdata', $this->fillNewAuthor(...));

        return $success;
    }

    /**
     * Name shown in the plugins list.
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.quickSubmitAuthorDefaults.displayName');
    }

    /**
     * Description shown in the plugins list.
     */
    public function getDescription(): string
    {
        return __('plugins.generic.quickSubmitAuthorDefaults.description');
    }

    /**
     * A switch of the journal, or its default while nothing was saved.
     */
    public function getFlag(?int $contextId, string $name): bool
    {
        if (!in_array($name, self::FLAGS, true)) {
            return false;
        }
        $value = $contextId === null ? null : $this->getSetting($contextId, $name);

        return $value === null || $value === '' ? self::DEFAULTS[$name] : (bool) $value;
    }

    /**
     * The address the journal chose for its contributors, or an empty string
     * when it chose none and its contact address is used.
     */
    public function getChosenEmail(?int $contextId): string
    {
        return $contextId === null ? '' : trim((string) $this->getSetting($contextId, 'email'));
    }

    /**
     * The role the journal chose for its contributors, or 0 when it chose none
     * and its default Author role is used.
     */
    public function getChosenUserGroupId(?int $contextId): int
    {
        return $contextId === null ? 0 : (int) $this->getSetting($contextId, 'userGroupId');
    }

    /**
     * The roles a contributor of this journal can have: the same list the
     * contributor form shows (PKPAuthorForm::fetch()).
     *
     * @return array<int, UserGroup>
     */
    public function getAuthorUserGroups(int $contextId): array
    {
        $userGroups = [];
        foreach (UserGroup::withRoleIds([Role::ROLE_ID_AUTHOR])->withContextIds([$contextId])->get() as $userGroup) {
            $userGroups[(int) $userGroup->id] = $userGroup;
        }
        ksort($userGroups);

        return $userGroups;
    }

    /**
     * The values a new contributor of this journal starts with, by name of the
     * field of the form. A value the journal does not have is left out, so the
     * field stays empty as it would without the plugin.
     *
     * @return array<string, int|string>
     */
    public function getDefaults(Context $context): array
    {
        $contextId = (int) $context->getId();
        $defaults = [];

        if ($this->getFlag($contextId, 'fillEmail')) {
            $email = $this->getChosenEmail($contextId) ?: trim((string) $context->getData('contactEmail'));
            if ($email !== '') {
                $defaults['email'] = $email;
            }
        }

        if ($this->getFlag($contextId, 'fillCountry')) {
            $country = trim((string) $context->getData('country'));
            if ($country !== '') {
                $defaults['country'] = $country;
            }
        }

        if ($this->getFlag($contextId, 'fillUserGroup')) {
            $userGroupId = $this->resolveUserGroupId($contextId, $this->getAuthorUserGroups($contextId));
            if ($userGroupId) {
                $defaults['userGroupId'] = $userGroupId;
            }
        }

        return $defaults;
    }

    /**
     * The role a new contributor starts with: the one the journal chose, while it
     * is still a role of contributors; otherwise the default Author role of the
     * journal; otherwise its first role of contributors. A journal with no such
     * role gets 0 and the field stays empty.
     *
     * @param array<int, UserGroup> $userGroups
     */
    public function resolveUserGroupId(int $contextId, array $userGroups): int
    {
        $chosen = $this->getChosenUserGroupId($contextId);
        if ($chosen && isset($userGroups[$chosen])) {
            return $chosen;
        }
        foreach ($userGroups as $id => $userGroup) {
            if ($userGroup->isDefault) {
                return $id;
            }
        }

        return (int) array_key_first($userGroups);
    }

    /**
     * The journal the form belongs to.
     */
    protected function contextOf(PKPAuthorForm $form): ?Context
    {
        return Application::get()->getRequest()->getContext();
    }

    /**
     * Hook authorform::initdata — fills the form of a new contributor.
     *
     * The form of an existing contributor is left as stored, and a field the
     * form already has a value for is never overwritten.
     *
     * @param array $args [$form]
     */
    public function fillNewAuthor(string $hookName, array $args): bool
    {
        $form = $args[0] ?? null;
        if (!$form instanceof PKPAuthorForm || $form->getAuthor()) {
            return Hook::CONTINUE;
        }
        $context = $this->contextOf($form);
        if (!$context) {
            return Hook::CONTINUE;
        }

        foreach ($this->getDefaults($context) as $name => $value) {
            if ($form->getData($name) === null || $form->getData($name) === '') {
                $form->setData($name, $value);
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * The settings action, while the plugin is enabled.
     *
     * @param \PKP\core\PKPRequest $request
     * @param array $actionArgs
     */
    public function getActions($request, $actionArgs): array
    {
        $router = $request->getRouter();

        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * Shows and saves the settings of the journal. There is nothing to set
     * without a journal.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $context = $request->getContext();
        if (!$context) {
            return new JSONMessage(false);
        }

        $form = new QuickSubmitAuthorDefaultsSettingsForm($this, $context);
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }
        $form->execute();

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification($request->getUser()->getId());

        return new JSONMessage(true);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\quickSubmitAuthorDefaults\QuickSubmitAuthorDefaultsPlugin', '\QuickSubmitAuthorDefaultsPlugin');
}
