<?php

/**
 * @license Master 1
 * @author Anthony TRIELLI. Yasmine SAIFI. Norhane KHALED
 */
class drop_drag_contact extends rcube_plugin
{
	public $task = 'mail';
	private $rc;

	function init()
	{
		$rc = rcmail::get_instance();
		if($rc->action == 'compose') 
		{
			$this->require_plugin('jqueryui');
			$this->include_script('drop_drag_contact.js');
		}
	}

}
