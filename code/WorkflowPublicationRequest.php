<?php
/**
 * A "publication request" is created when an author without
 * publish rights changes a page in draft mode, and explicitly
 * requests it to be reviewed for publication.
 * Each request can have one or more "Publishers" which
 * should have permissions to publish the specific page.
 *
 * @package cmsworkflow
 */
class WorkflowPublicationRequest extends WorkflowRequest implements i18nEntityProvider {

	/**
	 * @param string $emailtemplate_creation
	 */
	protected static $emailtemplate_awaitingapproval = 'PublicationAwaitingApprovalEmail';
	
	/**
	 * @param string $emailtemplate_approved
	 */
	protected static $emailtemplate_approved = 'PublicationApprovedEmail';
	
	/**
	 * @param string $emailtemplate_denied
	 */
	protected static $emailtemplate_denied = 'PublicationDeniedEmail';
	
	public static function create_for_page($page, $author = null, $publishers = null, $notify = true) {
		if(!$author && $author !== FALSE) $author = Member::currentUser();
	
		// take all members from the PublisherGroups relation on this record as a default
		if(!$publishers) $publishers = $page->PublisherMembers();

		// if no publishers are set, the request will end up nowhere
		if(!$publishers->Count()) {
			echo "No publishers selected\n";
			return null;
		}

		if(!self::can_create($author, $page)) {
			echo "No create permnission for $author->ID on $page->ID\n";
			return null;
		}
	
		// get or create a publication request
		$request = $page->OpenWorkflowRequest();
		if(!$request || !$request->ID) {
			$request = new WorkflowPublicationRequest();
			$request->PageID = $page->ID;
		}

		// @todo Check for correct workflow class (a "publication" request might be overwritten with a "deletion" request)

		// @todo reassign original author as a reviewer if present
		$request->AuthorID = $author->ID;
		$request->write();

		// assign publishers to this specific request
		foreach($publishers as $publisher) {
			$request->Publishers()->add($publisher);
		}

		// open the request and notify interested parties
		$request->Status = 'AwaitingApproval';
		$request->write();
		if($notify) $request->notifiyAwaitingApproval();
	
		return $request;
	}
	
	/**
	 * @param FieldSet $actions
	 * @parma SiteTree $page
	 */
	public static function update_cms_actions(&$actions, $page) {
		$openRequest = $page->OpenWorkflowRequest();

		// if user doesn't have publish rights, exchange the behavior from
		// "publish" to "request publish" etc.
		if(!$page->canPublish() || $openRequest) {

			// authors shouldn't be able to revert, as this republishes the page.
			// they should rather change the page and re-request publication
			$actions->removeByName('action_revert');

			// "request publication"
			$actions->removeByName('action_publish');
		}
		
		
		if(
			!$openRequest
			&& $page->canEdit() 
			&& $page->stagesDiffer('Stage', 'Live')
			&& $page->Version > 1 // page has been saved at least once
			&& !$page->IsDeletedFromStage
		) { 
			$actions->push(
				$requestPublicationAction = new FormAction(
					'cms_requestpublication', 
					_t('SiteTreeCMSWorkflow.BUTTONREQUESTPUBLICATION', 'Request Publication')
				)
			);
			// don't allow creation of a second request by another author
			if(!self::can_create(null, $page)) {
				$actions->makeFieldReadonly($requestPublicationAction->Name());
			}
		}
	}

	/**
	 * Approve a deletion request, deleting the page from the live site
	 */
	public function approve($comment, $member = null, $notify = true) {
		if(parent::approve($comment, $member, $notify)) {
			$this->Page()->doPublish();
			return true;
		}
	}
	
	/**
	 * @param Member $member
	 * @param SiteTree $page
	 * @return boolean
	 */
	public static function can_create($member = NULL, $page) {
		if(!$member && $member !== FALSE) {
			$member = Member::currentUser();
		}
		
		// if user can't edit page, he shouldn't be able to request publication
		if(!$page->canEdit($member)) return false;

		$request = $page->OpenWorkflowRequest();
		
		// if no request exists, allow creation of a new one (we can just have one open request at each point in time)
		if(!$request || !$request->ID) return true;
		
		// members can re-submit their own publication requests
		if($member && $member->ID == $request->AuthorID) return true;

		return true;
	}
	
	public function onAfterPublish($page, $publisher) {
		$this->PublisherID = $publisher->ID;
		$this->write();
		// open the request and notify interested parties
		$this->Status = 'Approved';
		$this->write();
		$this->notifyApproved();
	}
	
	function provideI18nEntities() {
		$entities = array();
		$entities["{$this->class}.EMAIL_SUBJECT_AWAITINGAPPROVAL"] = array(
			"Please review and publish the \"%s\" page on your site",
			PR_MEDIUM,
			'Email subject with page title'
		);
		$entities["{$this->class}.EMAIL_SUBJECT_APPROVED"] = array(
			"Your publication request for the \"%s\" page has been approved",
			PR_MEDIUM,
			'Email subject with page title'
		);
		$entities["{$this->class}.EMAIL_SUBJECT_DENIED"] = array(
			"Your publication request for the \"%s\" page has been denied",
			PR_MEDIUM,
			'Email subject with page title'
		);
		$entities["{$this->class}.EMAIL_SUBJECT_AWAITINGEDIT"] = array(
			"You are requested to review the \"%s\" page",
			PR_MEDIUM,
			'Email subject with page title'
		);
		
		return $entities;
	}
}
?>