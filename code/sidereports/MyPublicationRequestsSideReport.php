<?php
/**
 * Adds a new "sidereport" in the CMS listing all pages which require publication.
 * 
 * @package cmsworkflow
 */
class MyPublicationRequestsSideReport extends SideReport {
	function title() {
		return _t('PublisherReviewSideReport.TITLE',"Workflow: Awaiting publication");
	}
	function records() {
		if(Permission::check("ADMIN")) {
			return WorkflowRequest::get(
				'WorkflowPublicationRequest',
				array('AwaitingApproval')
			);
		} else {
			return WorkflowRequest::get_by_publisher(
				'WorkflowPublicationRequest',
				Member::currentUser(),
				array('AwaitingApproval')
			);
		}
	}
	function fieldsToShow() {
		return array(
			"Title" => array(
				"source" => array("NestedTitle", array("2")),
				"link" => true,
			),
		);
	}
}

?>