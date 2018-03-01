<?php
/**
 * ExpressionEngine (https://expressionengine.com)
 *
 * @link      https://expressionengine.com/
 * @copyright Copyright (c) 2003-2018, EllisLab, Inc. (https://ellislab.com)
 * @license   https://expressionengine.com/license
 */

namespace EllisLab\ExpressionEngine\Controller\Msm;

use CP_Controller;
use EllisLab\ExpressionEngine\Library\CP\Table;
use EllisLab\ExpressionEngine\Service\Validation\Result as ValidationResult;

/**
 * Multiple Site Manager Controller
 */
class Msm extends CP_Controller {

	protected $can_add = FALSE;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();

		ee('CP/Alert')->makeDeprecationNotice()->now();

		if (ee()->config->item('multiple_sites_enabled') !== 'y')
        {
			show_404();
        }

		ee()->lang->loadfile('sites');

		$license = ee('License')->getEELicense();
		$this->can_add = $license->canAddSites(ee('Model')->get('Site')->count());

		$this->stdHeader();
	}

	protected function stdHeader()
	{
		$header = array(
			'title' => lang('msm_manager'),
			'toolbar_items' => array(
				'settings' => array(
					'href' => ee('CP/URL')->make('settings/general'),
					'title' => lang('settings')
				)
			)
		);

		if ($this->can_add)
		{
			$header['action_button'] = [
				'text' => lang('add_site'),
				'href' => ee('CP/URL')->make('msm/create')
			];
		}

		ee()->view->header = $header;
	}

	public function index()
	{
		if ( count(ee()->session->userdata('assigned_sites')) == 0 )
		{
			show_error(lang('unauthorized_access'), 403);
		}

		if (ee()->input->post('bulk_action') == 'remove')
		{
			$this->remove(ee()->input->post('selection'));
			ee()->functions->redirect(ee('CP/URL')->make('msm'));
		}

		$base_url = ee('CP/URL')->make('msm');

		if ( ! $this->can_add)
		{
			ee('CP/Alert')->makeInline('site-limit-reached')
				->asIssue()
				->withTitle(lang('site_limit_reached'))
				->addToBody(sprintf(lang('site_limit_reached_desc'), 'https://expressionengine.com/store/purchases'))
				->cannotClose()
				->now();
		}

		$sites = ee('Model')->get('Site', array_keys(ee()->session->userdata('assigned_sites')))->all();

		$table = ee('CP/Table', array('autosort' => TRUE, 'autosearch' => TRUE));
		$table->setColumns(
			array(
				'col_id',
				'name',
				'short_name' => array(
					'encode' => FALSE
				),
				'status' => array(
					'type' => Table::COL_STATUS
				),
				'manage' => array(
					'type' => Table::COL_TOOLBAR
				),
				array(
					'type' => Table::COL_CHECKBOX
				)
			)
		);

		$data = array();

		$site_id = ee()->session->flashdata('site_id');

		foreach ($sites as $site)
		{
			if ($site->site_system_preferences->is_site_on == 'y')
			{
				$status = array(
					'class' => 'enable',
					'content' => lang('online')
				);
			}
			else
			{
				$status = array(
					'class' => 'disable',
					'content' => lang('offline')
				);
			}
			$edit_url = ee('CP/URL')->make('msm/edit/' . $site->site_id);
			$column = array(
				$site->site_id,
				array(
					'content' => $site->site_label,
					'href' => $edit_url
				),
				'<var>{' . htmlentities($site->site_name, ENT_QUOTES) . '}</var>',
				$status,
				array('tools' => array(
					'edit' => array(
						'href' => $edit_url,
						'title' => lang('edit')
					)
				)),
				array(
					'name' => 'selection[]',
					'value' => $site->site_id,
					'data' => array(
						'confirm' => lang('site') . ': <b>' . htmlentities($site->site_label, ENT_QUOTES, 'UTF-8') . '</b>'
					)
				)
			);

			if ($site->site_id == 1)
			{
				$column[5]['disabled'] = TRUE;
			}

			$attrs = array();

			if ($site_id && $site->site_id == $site_id)
			{
				$attrs = array('class' => 'selected');
			}

			$data[] = array(
				'attrs'		=> $attrs,
				'columns'	=> $column
			);
		}

		$table->setData($data);

		$vars['table'] = $table->viewData($base_url);

		$vars['pagination'] = ee('CP/Pagination', $vars['table']['total_rows'])
			->perPage($vars['table']['limit'])
			->currentPage($vars['table']['page'])
			->render($vars['table']['base_url']);

		ee()->javascript->set_global('lang.remove_confirm', lang('site') . ': <b>### ' . lang('sites') . '</b>');
		ee()->cp->add_js_script(array(
			'file' => array(
				'cp/confirm_remove',
			),
		));

		ee()->view->cp_page_title = lang('sites');

		ee()->cp->render('msm/index', $vars);
	}

	public function create()
	{
		if ( ! ee()->cp->allowed_group('can_admin_sites')) // permission not currently setable, thus admin only
		{
			show_error(lang('unauthorized_access'), 403);
		}

		if ( ! $this->can_add && ! empty($_POST))
		{
			show_error(lang('unauthorized_access'), 403);
		}

		ee()->view->cp_breadcrumbs = array(
			ee('CP/URL')->make('msm')->compile() => lang('msm_manager'),
		);

		$errors = NULL;
		$site = ee('Model')->make('Site');
		$site->site_bootstrap_checksums = array();
		$site->site_pages = array();

		$result = $this->validateSite($site);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$site->save();

				ee()->session->set_flashdata('site_id', $site->site_id);

				ee()->logger->log_action(lang('site_created') . ': ' . $site->site_label);

				ee('CP/Alert')->makeInline('shared-form')
					->asSuccess()
					->withTitle(lang('create_site_success'))
					->addToBody(sprintf(lang('create_site_success_desc'), $site->site_label))
					->defer();

				if (ee('Request')->post('submit') == 'save_and_new')
				{
					ee()->functions->redirect(ee('CP/URL')->make('msm/create'));
				}
				elseif (ee()->input->post('submit') == 'save_and_close')
				{
					ee()->functions->redirect(ee('CP/URL')->make('msm'));
				}
				else
				{
					ee()->functions->redirect(ee('CP/URL')->make('msm/edit/' . $site->getId()));
				}
			}
			else
			{
				ee('CP/Alert')->makeInline('shared-form')
					->asIssue()
					->withTitle(lang('create_site_error'))
					->addToBody(lang('create_site_error_desc'))
					->now();
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'base_url' => ee('CP/URL')->make('msm/create'),
			'errors' => $errors,
			'buttons' => [
				[
					'name' => 'submit',
					'type' => 'submit',
					'value' => 'save',
					'text' => 'save',
					'working' => 'btn_saving'
				],
				[
					'name' => 'submit',
					'type' => 'submit',
					'value' => 'save_and_new',
					'text' => 'save_and_new',
					'working' => 'btn_saving'
				],
				[
					'name' => 'submit',
					'type' => 'submit',
					'value' => 'save_and_close',
					'text' => 'save_and_close',
					'working' => 'btn_saving'
				]

			],
			'sections' => $this->getForm($site, $this->can_add),
		);

		if ( ! $this->can_add)
		{
			$vars['buttons'] = array(
				array(
					'text' => 'btn_site_limit_reached',
					'working' => 'btn_site_limit_reached',
					'value' => 'btn_site_limit_reached',
					'class' => 'disable',
					'name' => 'submit',
					'type' => 'submit'
				)
			);
		}

		ee()->view->cp_page_title = lang('create_site');

		if ($this->can_add)
		{
			ee()->cp->add_js_script('plugin', 'ee_url_title');
			ee()->javascript->output('
				$("input[name=site_label]").bind("keyup keydown", function() {
					$(this).ee_url_title("input[name=site_name]");
				});
			');
		}

		ee()->cp->render('settings/form', $vars);
	}

	public function edit($site_id)
	{
		if ( ! ee()->cp->allowed_group('can_admin_sites'))
		{
			show_error(lang('unauthorized_access'), 403);
		}

		$site = ee('Model')->get('Site', $site_id)->first();

		if ( ! $site)
		{
			show_404();
		}

		ee()->view->cp_breadcrumbs = array(
			ee('CP/URL')->make('msm')->compile() => lang('msm_manager'),
		);

		$errors = NULL;
		$result = $this->validateSite($site);

		if ($result instanceOf ValidationResult)
		{
			$errors = $result;

			if ($result->isValid())
			{
				$site->save();

				ee('CP/Alert')->makeInline('shared-form')
					->asSuccess()
					->withTitle(lang('edit_site_success'))
					->addToBody(sprintf(lang('edit_site_success_desc'), $site->site_label))
					->defer();

				ee()->logger->log_action(lang('site_updated') . ': ' . $site->site_label);

				if (ee('Request')->post('submit') == 'save_and_new')
				{
					ee()->functions->redirect(ee('CP/URL')->make('msm/create'));
				}
				elseif (ee()->input->post('submit') == 'save_and_close')
				{
					ee()->functions->redirect(ee('CP/URL')->make('msm'));
				}
				else
				{
					ee()->functions->redirect(ee('CP/URL')->make('msm/edit/' . $site_id));
				}
			}
			else
			{
				ee('CP/Alert')->makeInline('shared-form')
					->asIssue()
					->withTitle(lang('edit_site_error'))
					->addToBody(lang('edit_site_error_desc'))
					->now();
			}
		}

		$vars = array(
			'ajax_validate' => TRUE,
			'base_url' => ee('CP/URL')->make('msm/edit/' . $site_id),
			'errors' => $errors,
			'buttons' => [
				[
					'name' => 'submit',
					'type' => 'submit',
					'value' => 'save',
					'text' => 'save',
					'working' => 'btn_saving'
				],
				[
					'name' => 'submit',
					'type' => 'submit',
					'value' => 'save_and_new',
					'text' => 'save_and_new',
					'working' => 'btn_saving'
				],
				[
					'name' => 'submit',
					'type' => 'submit',
					'value' => 'save_and_close',
					'text' => 'save_and_close',
					'working' => 'btn_saving'
				]

			],
			'sections' => $this->getForm($site, TRUE),
		);

		if ( ! $this->can_add)
		{
			unset($vars['buttons'][1]);
		}

		ee()->view->cp_page_title = lang('edit_site');

		ee()->cp->render('settings/form', $vars);
	}

	/**
	 * Prepares and returns an array for the 'sections' view variable of the
	 * shared/form view.
	 *
	 * @param Site $site A Site entity for populating the values of this form
	 * @param bool $can_add Have they reached their site limit?
	 */
	private function getForm($site, $can_add = FALSE)
	{
		$sections = array(array());

		$disabled = ! $can_add;

		if ( ! $can_add)
		{
			$alert = ee('CP/Alert')->makeInline('site-limit-reached')
				->asIssue()
				->withTitle(lang('site_limit_reached'))
				->addToBody(sprintf(lang('site_limit_reached_desc'), 'https://expressionengine.com/store/purchases'))
				->cannotClose()
				->render();
			$sections[0][] = $alert;
		}

		$name = array(
			'title' => 'name',
			'desc' => 'name_desc',
			'fields' => array(
				'site_label' => array(
					'type' => 'text',
					'value' => $site->site_label ?: '',
					'required' => TRUE,
					'disabled' => $disabled
				)
			)
		);
		$sections[0][] = $name;

		$short_name = array(
			'title' => 'short_name',
			'desc' => 'short_name_desc',
			'fields' => array(
				'site_name' => array(
					'type' => 'text',
					'value' => $site->site_name ?: '',
					'required' => TRUE,
					'disabled' => $disabled
				)
			)
		);
		$sections[0][] = $short_name;

		if ( ! $site->isNew())
		{
			$site_online = array(
				'title' => 'site_online',
				'desc' => 'site_online_desc',
				'fields' => array(
					'is_site_on' => array(
						'type' => 'yes_no',
						'value' => $site->site_system_preferences->is_site_on
					)
				)
			);
			$sections[0][] = $site_online;
		}

		$description = array(
			'title' => 'description',
			'desc' => 'description_desc',
			'fields' => array(
				'site_description' => array(
					'type' => 'textarea',
					'value' => $site->site_description,
					'disabled' => $disabled
				)
			)
		);
		$sections[0][] = $description;

		return $sections;
	}

	/**
	 * Validates the Site entity returning JSON if it was an AJAX request, or
	 * sets an appropriate alert and returns the validation result.
	 *
	 * @param Site $site A Site entity to validate
	 * @return Mixed If nothing was posted: FALSE; if AJAX: void; otherwise a
	 *   Result object
	 */
	private function validateSite($site)
	{
		if (empty($_POST))
		{
			return FALSE;
		}

		$action = ($site->isNew()) ? 'create' : 'edit';

		$site->set($_POST);

		if ($action == 'edit')
		{
			$site->site_system_preferences->is_site_on = ee()->input->post('is_site_on');
		}

		$result = $site->validate();

		if ($response = $this->ajaxValidation($result))
		{
			ee()->output->send_ajax_response($response);
		}

		if ($result->failed())
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang($action . '_site_error'))
				->addToBody(lang($action . '_site_error_desc'))
				->now();
		}

		return $result;
	}

	public function switchTo($site_id)
	{
		if ( ! is_numeric($site_id))
		{
			show_404();
		}

		ee()->cp->switch_site($site_id);
	}

	private function remove($site_ids)
	{
		if ( ! is_array($site_ids))
		{
			$site_ids = array($site_ids);
		}

		if (in_array(1, $site_ids))
		{
			$site = ee('Model')->get('Site', 1)
				->fields('site_label')
				->first();
			show_error(sprintf(lang('cannot_remove_site_1'), $site->site_label));
		}

		$sites = ee('Model')->get('Site', $site_ids)->all();

		$site_names = $sites->pluck('site_label');

		$sites->delete();

		foreach ($site_names as $site_name)
		{
			ee()->logger->log_action(lang('site_deleted') . ': ' . $site_name);
		}

		ee('CP/Alert')->makeInline('sites')
			->asSuccess()
			->withTitle(lang('success'))
			->addToBody(lang('sites_removed_desc'))
			->addToBody($site_names)
			->defer();

		// Refresh Sites List
		$assigned_sites = array();

		if (ee()->session->userdata['group_id'] == 1)
		{
			$result = ee('Model')->get('Site')
				->fields('site_id', 'site_label')
				->order('site_label', 'asc')
				->all();
		}
		elseif (ee()->session->userdata['assigned_sites'] != '')
		{
			$result = ee('Model')->get('Site')
				->fields('site_id', 'site_label')
				->filter('site_id', explode('|', ee()->session->userdata['assigned_sites']))
				->order('site_label', 'asc')
				->all();
		}

		if ((ee()->session->userdata['group_id'] == 1 OR ee()->session->userdata['assigned_sites'] != '') && count($result) > 0)
		{
			$assigned_sites = $result->getDictionary('site_id', 'site_label');
		}

		ee()->session->userdata['assigned_sites'] = $assigned_sites;
	}
}

// EOF
