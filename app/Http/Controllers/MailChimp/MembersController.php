<?php
declare(strict_types=1);

namespace App\Http\Controllers\MailChimp;

use App\Database\Entities\MailChimp\MailChimpList;
use App\Database\Entities\MailChimp\MailChimpMember;
use App\Http\Controllers\Controller;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mailchimp\Mailchimp;

class MembersController extends Controller
{
    /**
     * @var \Mailchimp\Mailchimp
     */
    private $mailChimp;

    /**
     * ListsController constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     * @param \Mailchimp\Mailchimp $mailchimp
     */
    public function __construct(EntityManagerInterface $entityManager, Mailchimp $mailchimp)
    {
        parent::__construct($entityManager);

        $this->mailChimp = $mailchimp;
    }

    /**
     * Add MailChimp List Member.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request, string $mailChimpId): JsonResponse
    //public function create(Request $request): JsonResponse
    {

        /** @var \App\Database\Entities\MailChimp\MailChimpList|null $list */
       // $list = $this->entityManager->getRepository(MailChimpList::class)->find($mailChimpId);

        // $list = $this->entityManager->getRepository(MailChimpList::class)->find('mail_chimp_id', $mailChimpId);
        // if ($list === null) {
        //     return $this->errorResponse(
        //         ['message' => \sprintf('MailChimpList[%s] not found', $mailChimpId)],
        //         404
        //     );
        // }

        // Instantiate entity
        $member = new MailChimpMember($request->all());
        // Validate entity
        $validator = $this->getValidationFactory()->make($member->toMailChimpArray(), $member->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Save member into db
            $this->saveEntity($member);
            // Save member into MailChimp
            $response = $this->mailChimp->post(\sprintf('lists/%s/members', $mailChimpId), $member->toMailChimpArray());
            //$response = $this->mailChimp->post(\sprintf('lists/%s/members', $request->mail_chimp_id), $member->toMailChimpArray());
            // Set Subscriber Hash and save update member subscriber hash
            $this->saveEntity($member->setSubscriberHash($response->get('id')));
        } catch (Exception $exception) {
            // Return error response if something goes wrong
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($member->toArray());
    }

    /**
     * Remove MailChimp list member.
     *
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    //public function remove(string $listId, string $subscriberHash): JsonResponse
    public function remove(string $memberId): JsonResponse
    {
        /** @var \App\Database\Entities\MailChimp\MailChimpList|null $list */
        $member = $this->entityManager->getRepository(MailChimpMember::class)->find($memberId);

        if (!$member) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpMember[%s] not found', $memberId)],
                404
            );
        }
 
        try {
            // Remove member from database
            $this->removeEntity($member);
            // Remove member from MailChimp
            $this->mailChimp->delete(\sprintf('lists/%s/members/%s', $member->getListId(), $member->getSubscriberHash()));

            //$this->mailChimp->delete(\sprintf('lists/%s/members/%s', $member->getListId(), $subscriberHash));
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse([]);
        //return $this->successfulResponse($member->toArray());
    }

    /**
     * Retrieve and return MailChimp member.
     *
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(string $subscriberHash): JsonResponse
    {
        /** @var \App\Database\Entities\MailChimp\MailChimpMember|null $list */
        $member = $this->entityManager->getRepository(MailChimpMember::class)->find($subscriberHash);

        if ($member === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpMember[%s] not found', $subscriberHash)],
                404
            );
        }

        return $this->successfulResponse($member->toArray());
    }

    /**
     * Update Mailchimp list member
     *
     * @param \Illuminate\Http\Request $request
     * @param string $listId
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, string $memberId): JsonResponse
    {
        /** @var \App\Database\Entities\MailChimp\MailChimpMember|null $member */
        $member = $this->entityManager->getRepository(MailChimpMember::class)->find($memberId);

        if ($member === null) {
            return $this->errorResponse(
                ['message' => \sprintf('MailChimpMember[%s] not found', $memberId)],
                404
            );
        }

        // Update member properties
        $member->fill($request->all());

        // Validate entity
        $validator = $this->getValidationFactory()->make($member->toMailChimpArray(), $member->getValidationRules());

        if ($validator->fails()) {
            // Return error response if validation failed
            return $this->errorResponse([
                'message' => 'Invalid data given',
                'errors' => $validator->errors()->toArray()
            ]);
        }

        try {
            // Update member into database
            $this->saveEntity($member);
            // Update member into MailChimp
            $this->mailChimp->patch(\sprintf('/lists/%s/members/%s', $member->getListId(), $member->getSubscriberHash()));
        } catch (Exception $exception) {
            return $this->errorResponse(['message' => $exception->getMessage()]);
        }

        return $this->successfulResponse($member->toArray());
    }
}
