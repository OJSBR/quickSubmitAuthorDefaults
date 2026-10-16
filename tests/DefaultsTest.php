<?php

/**
 * @file plugins/generic/quickSubmitAuthorDefaults/tests/DefaultsTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DefaultsTest
 *
 * @brief What a new contributor starts with, the settings that decide it, and
 *        that only a new contributor is touched — through the real hook the
 *        core fires from the real contributor form.
 */

namespace APP\plugins\generic\quickSubmitAuthorDefaults\tests;

use APP\author\Author;
use APP\controllers\grid\users\author\form\AuthorForm;
use APP\journal\Journal;
use APP\plugins\generic\quickSubmitAuthorDefaults\QuickSubmitAuthorDefaultsPlugin;
use APP\publication\Publication;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\context\Context;
use PKP\controllers\grid\users\author\form\PKPAuthorForm;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

#[CoversClass(QuickSubmitAuthorDefaultsPlugin::class)]
class DefaultsTest extends PKPTestCase
{
    public const HOOK = 'authorform::initdata';

    protected function tearDown(): void
    {
        Hook::clear(self::HOOK);
        parent::tearDown();
    }

    /** A journal that exists only in memory. */
    protected function journal(array $data = []): Journal
    {
        $journal = new Journal();
        $journal->setId(7);
        $journal->setData('contactEmail', 'editor@journal.example');
        $journal->setData('supportEmail', 'support@journal.example');
        $journal->setData('country', 'BR');
        foreach ($data as $name => $value) {
            $journal->setData($name, $value);
        }

        return $journal;
    }

    /**
     * A plugin with the given settings and roles of contributors, which never
     * reaches the database.
     *
     * @param array<int, object> $userGroups
     */
    protected function plugin(array $settings = [], ?array $userGroups = null, ?Context $context = null): QuickSubmitAuthorDefaultsPlugin
    {
        $userGroups ??= [14 => (object) ['isDefault' => true], 15 => (object) ['isDefault' => false]];

        return new class ($settings, $userGroups, $context) extends QuickSubmitAuthorDefaultsPlugin {
            public function __construct(private array $settings, private array $userGroups, private ?Context $context)
            {
                parent::__construct();
            }

            public function getSetting($contextId, $name)
            {
                return $this->settings[$name] ?? null;
            }

            public function getAuthorUserGroups(int $contextId): array
            {
                return $this->userGroups;
            }

            protected function contextOf(PKPAuthorForm $form): ?Context
            {
                return $this->context;
            }
        };
    }

    /** The legacy contributor form of the Quick Submit plugin. */
    protected function form(?Author $author = null): AuthorForm
    {
        $publication = new Publication();
        $publication->setData('locale', 'en');

        return new AuthorForm($publication, $author);
    }

    public function testANewContributorStartsWithTheJournalValuesAndTheAuthorRole(): void
    {
        $this->assertSame(
            ['email' => 'support@journal.example', 'country' => 'BR', 'userGroupId' => 14],
            $this->plugin()->getDefaults($this->journal())
        );
    }

    public function testWithoutATechnicalSupportContactThePrincipalContactIsUsed(): void
    {
        // The technical support contact is optional in OJS; the principal contact is not.
        $this->assertSame('editor@journal.example', $this->plugin()->getDefaults($this->journal(['supportEmail' => null]))['email']);
        $this->assertSame('editor@journal.example', $this->plugin()->getDefaults($this->journal(['supportEmail' => '  ']))['email']);
    }

    public function testTheAddressChosenByTheJournalWinsOverItsSupportAddress(): void
    {
        $defaults = $this->plugin(['email' => ' authors@journal.example '])->getDefaults($this->journal());

        $this->assertSame('authors@journal.example', $defaults['email']);
    }

    public function testEachFieldCanBeSwitchedOff(): void
    {
        $this->assertArrayNotHasKey('email', $this->plugin(['fillEmail' => false])->getDefaults($this->journal()));
        $this->assertArrayNotHasKey('country', $this->plugin(['fillCountry' => false])->getDefaults($this->journal()));
        $this->assertArrayNotHasKey('userGroupId', $this->plugin(['fillUserGroup' => false])->getDefaults($this->journal()));
        $this->assertSame([], $this->plugin(['fillEmail' => 0, 'fillCountry' => 0, 'fillUserGroup' => 0])->getDefaults($this->journal()));
    }

    public function testWhatTheJournalLacksStaysEmpty(): void
    {
        $defaults = $this->plugin([], [])->getDefaults($this->journal(['supportEmail' => '', 'contactEmail' => '', 'country' => null]));

        $this->assertSame([], $defaults);
    }

    public function testTheRoleIsTheChosenOneWhileItIsStillARoleOfContributors(): void
    {
        $groups = [14 => (object) ['isDefault' => true], 15 => (object) ['isDefault' => false]];

        $this->assertSame(15, $this->plugin(['userGroupId' => 15])->resolveUserGroupId(7, $groups));
        // A role that was deleted, or that is not a role of contributors, falls back to the default.
        $this->assertSame(14, $this->plugin(['userGroupId' => 3])->resolveUserGroupId(7, $groups));
        // A journal without a default role of contributors gets its first one.
        $this->assertSame(15, $this->plugin()->resolveUserGroupId(7, [15 => (object) ['isDefault' => false], 16 => (object) ['isDefault' => false]]));
        $this->assertSame(0, $this->plugin()->resolveUserGroupId(7, []));
    }

    public function testTheFormOfANewContributorOpensFilledThroughTheCoreHook(): void
    {
        $plugin = $this->plugin([], null, $this->journal());
        Hook::add(self::HOOK, $plugin->fillNewAuthor(...));

        $form = $this->form();
        $form->initData();

        $this->assertSame('support@journal.example', $form->getData('email'));
        $this->assertSame('BR', $form->getData('country'));
        $this->assertSame(14, $form->getData('userGroupId'));
        // What the core sets for a new contributor is kept.
        $this->assertTrue($form->getData('includeInBrowse'));
    }

    public function testWithoutThePluginTheFormOpensEmpty(): void
    {
        // Counterproof: the same form, with nothing listening to the hook.
        $form = $this->form();
        $form->initData();

        $this->assertNull($form->getData('email'));
        $this->assertNull($form->getData('country'));
        $this->assertNull($form->getData('userGroupId'));
    }

    public function testTheFormOfAnExistingContributorIsLeftAlone(): void
    {
        // Loading an existing contributor reads its affiliation from the database,
        // so the callback is called the way the core calls it, on an empty form.
        $author = new Author();
        $author->setId(99);
        $form = $this->form($author);

        $this->assertSame(Hook::CONTINUE, $this->plugin([], null, $this->journal())->fillNewAuthor(self::HOOK, [$form]));
        $this->assertNull($form->getData('email'));
        $this->assertNull($form->getData('country'));
        $this->assertNull($form->getData('userGroupId'));
    }

    public function testAValueTheFormAlreadyHasIsNeverOverwritten(): void
    {
        $plugin = $this->plugin([], null, $this->journal());
        $form = $this->form();
        $form->setData('email', 'typed@by.editor');

        $plugin->fillNewAuthor(self::HOOK, [$form]);

        $this->assertSame('typed@by.editor', $form->getData('email'));
        $this->assertSame('BR', $form->getData('country'));
    }

    public function testNothingHappensOutsideAJournal(): void
    {
        $plugin = $this->plugin();
        $form = $this->form();

        $this->assertSame(Hook::CONTINUE, $plugin->fillNewAuthor(self::HOOK, [$form]));
        $this->assertNull($form->getData('email'));
    }
}
