<?php
declare(strict_types=1);

namespace Tests\App\Functional\Http\Controllers\MailChimp;

use Tests\App\TestCases\MailChimp\MemberTestCase;

class MembersControllerTest extends MemberTestCase
{

    /**
     * Test application creates successfully list and returns it back with id from MailChimp.
     *
     * @return void
     */
    public function testCreateMemberSuccessfully(): void
    {

        $this->post('/mailchimp/lists', static::$listData);
        $list = \json_decode($this->response->content(), true);

        if (isset($list['mail_chimp_id'])) {
            $this->createdListIds[] = $list['mail_chimp_id']; // Store MailChimp list id for cleaning purposes
        }

        $this->post(\sprintf('/mailchimp/members/%s', $list['mail_chimp_id']), static::$memberData);
        $content = \json_decode($this->response->getContent(), true);

        $this->assertResponseOk();
        $this->seeJson(static::$memberData);
        self::assertArrayHasKey('subscriber_hash', $content);
        self::assertNotNull($content['subscriber_hash']);

        if (isset($content['subscriber_hash'])) {
            $this->createdMembersSubscriberHash[] = $content['subscriber_hash']; // Store MailChimp member subscriber_hash for cleaning purposes
        }

    }

    /**
     * Test application returns error response with errors when list validation fails.
     *
     * @return void
     */
    public function testCreateListValidationFailed(): void
    {
        $this->post(\sprintf('/mailchimp/members/%s', 'foo'));

        $content = \json_decode($this->response->getContent(), true);

        $this->assertResponseStatus(400);
        self::assertArrayHasKey('message', $content);
        self::assertArrayHasKey('errors', $content);
        self::assertEquals('Invalid data given', $content['message']);

        foreach (\array_keys(static::$memberData) as $key) {
            // if (\in_array($key, static::$notRequired, true)) {
            //     continue;
            // }

            self::assertArrayHasKey($key, $content['errors']);
        }
    }

    /**
     * Test application returns error response when list not found.
     *
     * @return void
     */
    public function testRemoveMemberNotFoundException(): void
    {
        $this->delete('/mailchimp/members/invalid-member-id');

        $this->assertMemberNotFoundResponse('invalid-member-id');
    }

    /**
     * Test application returns empty successful response when removing existing list.
     *
     * @return void
     */

    public function testRemoveMemberSuccessfully(): void
    {
        //create list
        $this->post('/mailchimp/lists', static::$listData);
        $list = \json_decode($this->response->content(), true);

        if (isset($list['mail_chimp_id'])) {
            $this->createdListIds[] = $list['mail_chimp_id']; // Store MailChimp list id for cleaning purposes
        }

        //create member
        $this->post(\sprintf('/mailchimp/members/%s', $list['mail_chimp_id']), static::$memberData);
        $member = \json_decode($this->response->getContent(), true);

        if (isset($member['subscriber_hash'])) {
            $this->createdMembersSubscriberHash[] = $member['subscriber_hash']; // Store MailChimp member subscriber_hash for cleaning purposes
        }

        $createMember = $this->createMember([
            'list_id' => $list['mail_chimp_id'],
            'email_address' => static::$memberData['email_address'],
            'status' => static::$memberData['status'],
            'subscriber_hash' => $member['subscriber_hash']
        ]);

        //delete member
        $this->delete(\sprintf('/mailchimp/members/%s', $createMember->getId()));

        $this->assertResponseOk();
        self::assertEmpty(\json_decode($this->response->content(), true));
    }


    /**
     * Test application returns error response when member not found.
     *
     * @return void
     */
    public function testShowMemberNotFoundException(): void
    {
        $this->get('/mailchimp/members/invalid-member-id');

        $this->assertMemberNotFoundResponse('invalid-member-id');
    }

    /**
     * Test application returns successful response with member data when requesting existing member.
     *
     * @return void
     */
    public function testShowListSuccessfully(): void
    {
        $member = $this->createMember(static::$memberData);

        $this->get(\sprintf('/mailchimp/members/%s', $member->getId()));
        $content = \json_decode($this->response->content(), true);

        $this->assertResponseOk();

        foreach (static::$memberData as $key => $value) {
            self::assertArrayHasKey($key, $content);
            self::assertEquals($value, $content[$key]);
        }
    }

    /**
     * Test application returns error response when member not found.
     *
     * @return void
     */
    public function testUpdateListNotFoundException(): void
    {
        $this->put('/mailchimp/members/invalid-member-id');

        $this->assertMemberNotFoundResponse('invalid-member-id');
    }

    /**
     * Test application returns successfully response when updating existing list with updated values.
     *
     * @return void
     */
    public function testUpdateMemberSuccessfully(): void
    {
        //create list
        $this->post('/mailchimp/lists', static::$listData);
        $list = \json_decode($this->response->content(), true);

        if (isset($list['mail_chimp_id'])) {
            $this->createdListIds[] = $list['mail_chimp_id']; // Store MailChimp list id for cleaning purposes
        }

        //create member
        $this->post(\sprintf('/mailchimp/members/%s', $list['mail_chimp_id']), static::$memberData);
        $member = \json_decode($this->response->getContent(), true);

        if (isset($member['subscriber_hash'])) {
            $this->createdMembersSubscriberHash[] = $member['subscriber_hash']; // Store MailChimp member subscriber_hash for cleaning purposes
        }
        
        $createMember = $this->createMember([
            'list_id' => $list['mail_chimp_id'],
            'email_address' => static::$memberData['email_address'],
            'status' => static::$memberData['status'],
            'subscriber_hash' => $member['subscriber_hash']
        ]);

        $this->put(\sprintf('/mailchimp/members/%s', $createMember->getId()), ['email_address' => 'update@mail.com']);
        $content = \json_decode($this->response->content(), true);

        $this->assertResponseOk();

        foreach (\array_keys(static::$memberData) as $key) {
            self::assertArrayHasKey($key, $content);
            self::assertEquals('update@mail.com', $content['email_address']);
        }
    }

    /**
     * Test application returns error response with errors when list validation fails.
     *
     * @return void
     */
    public function testUpdateMemberValidationSuccess(): void
    {
        //create list
        $this->post('/mailchimp/lists', static::$listData);
        $list = \json_decode($this->response->content(), true);

        if (isset($list['mail_chimp_id'])) {
            $this->createdListIds[] = $list['mail_chimp_id']; // Store MailChimp list id for cleaning purposes
        }

        //create member
        $this->post(\sprintf('/mailchimp/members/%s', $list['mail_chimp_id']), static::$memberData);
        $member = \json_decode($this->response->getContent(), true);

        if (isset($member['subscriber_hash'])) {
            $this->createdMembersSubscriberHash[] = $member['subscriber_hash']; // Store MailChimp member subscriber_hash for cleaning purposes
        }
        
        $createMember = $this->createMember([
            'list_id' => $list['mail_chimp_id'],
            'email_address' => static::$memberData['email_address'],
            'status' => static::$memberData['status'],
            'subscriber_hash' => $member['subscriber_hash']
        ]);

        $this->put(\sprintf('/mailchimp/members/%s', $createMember->getId()), []);

        $content = \json_decode($this->response->content(), true);

        $this->assertResponseStatus(200);

    }
}
