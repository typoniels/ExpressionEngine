<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		ExpressionEngine Dev Team
 * @copyright	Copyright (c) 2003 - 2011, EllisLab, Inc.
 * @license		http://expressionengine.com/user_guide/license.html
 * @link		http://expressionengine.com
 * @since		Version 2.4
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine Rich Text Editor Module
 *
 * @package		ExpressionEngine
 * @subpackage	Modules
 * @category	Modules
 * @author		Aaron Gustafson
 * @link		http://easy-designs.net
 */
class Rte_mcp {

	public $name = 'Rte';

	/**
	 * Constructor
	 *
	 * @access	public
	 */
	public function __construct()
	{
		// Make a local reference to the ExpressionEngine super object
		$this->EE =& get_instance();

		// Let's make sure they're allowed...
		$this->_permissions_check();

		// Load it all
		$this->EE->load->helper('form');
		$this->EE->load->library('rte_lib');
		$this->EE->load->model('rte_tool_model');

		// set some properties
		$this->_base_url	= BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=rte';
		$this->_form_base	= 'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=rte';
		$this->EE->rte_lib->cancel_url = $this->_base_url;
		$this->EE->rte_lib->form_url = $this->_form_base;

		// Load all tools into the DB
		$this->EE->rte_tool_model->load_tools_into_db();
	}

	// --------------------------------------------------------------------

	/**
	 * Homepage
	 *
	 * @access	public
	 * @return	string The page
	 */
	public function index()
	{
		// dependencies
		$this->EE->load->library(array('table','javascript'));
		$this->EE->load->model('rte_toolset_model');

		// set up the page
		$this->EE->cp->set_right_nav(array(
			'create_new_rte_toolset' => $this->_base_url.AMP.'method=edit_toolset'
		));

		$vars = array(
			'cp_page_title'				=> lang('rte_module_name'),
			'module_base'				=> $this->_base_url,
			'action'					=> $this->_form_base.AMP.'method=prefs_update',
			'rte_enabled'				=> $this->EE->config->item('rte_enabled'),
			'rte_default_toolset_id'	=> $this->EE->config->item('rte_default_toolset_id'),
			'toolset_opts'				=> $this->EE->rte_toolset_model->get_active(TRUE),
			'toolsets'					=> $this->EE->rte_toolset_model->get_all(),
			'tools'						=> $this->EE->rte_tool_model->get_all()
		);
		
		// JS
		$this->EE->cp->add_js_script(array(
			'file'		=> 'cp/rte',
			'plugin'	=> array('overlay', 'toolbox.expose')
		));
		$this->EE->javascript->set_global(array(
			'rte'	=> array(
				'name_required'				=> lang('name_required'),
				'validate_toolset_name_url'	=> BASE.AMP.'C=myaccount'.AMP.'M=custom_action'.AMP.'extension=rte'.AMP.'method=validate_toolset_name',
			)
		));
		$this->EE->javascript->compile();
		
		// CSS
		$this->EE->cp->add_to_head($this->EE->view->head_link('css/rte.css'));
		
		// return the page
		return $this->EE->load->view('index', $vars, TRUE);
	}
	
	
	// --------------------------------------------------------------------
	
	/**
	 * Update prefs form action
	 *
	 * @access	public
	 * @return	void
	 */
	public function prefs_update()
	{
		// set up the validation
		$this->EE->load->library('form_validation');
		$this->EE->form_validation->set_rules(
			'rte_enabled',
			lang('enabled_question'),
			'required|enum[y,n]'
		);

		$this->EE->form_validation->set_rules(
			'rte_default_toolset_id',
			lang('choose_default_toolset'),
			'required|is_numeric'
		);
		
		if ($this->EE->form_validation->run())
		{
			// update the prefs
			$this->_do_update_prefs();
			$this->EE->session->set_flashdata('message_success', lang('settings_saved'));
		}
		else
		{
			$this->EE->session->set_flashdata('message_failure', lang('settings_not_saved'));
		}
		
		$this->EE->functions->redirect($this->_base_url);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Provides Edit Toolset Screen HTML
	 *
	 * @access	public
	 * @param	int $toolset_id The Toolset ID to be edited (optional)
	 * @return	string The page
	 */
	public function edit_toolset($toolset_id = FALSE)
	{
		return $this->EE->rte_lib->edit_toolset($toolset_id);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Saves a toolset
	 *
	 * @access	public
	 * @return	void
	 */
	public function save_toolset()
	{
		$this->EE->rte_lib->save_toolset();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Enables a toolset
	 *
	 * @access	public
	 * @return	void
	 */
	public function enable_toolset()
	{
		$this->_update_toolset(
			$this->EE->input->get_post('rte_toolset_id'),
			array( 'enabled' => 'y' ),
			lang('toolset_enabled'),
			lang('toolset_update_failed')
		);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Disables a toolset
	 *
	 * @access	public
	 * @return	void
	 */
	public function disable_toolset()
	{		
		$this->_update_toolset(
			$this->EE->input->get_post('rte_toolset_id'),
			array( 'enabled' => 'n' ),
			lang('toolset_disabled'),
			lang('toolset_update_failed')
		);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Deletes a toolset
	 *
	 * @access	public
	 * @return	void
	 */
	public function delete_toolset()
	{
		$this->EE->load->model('rte_toolset_model');
		
		// delete
		if ($this->EE->rte_toolset_model->delete($this->EE->input->get_post('rte_toolset_id')))
		{
			$this->EE->session->set_flashdata('message_success', lang('toolset_deleted'));
		}
		else
		{
			$this->EE->session->set_flashdata('message_failure', lang('toolset_not_deleted'));
		}
		
		$this->EE->functions->redirect($this->_base_url);
	}

	// --------------------------------------------------------------------
	
	/**
	 * Validates a toolset name for existance and uniqueness
	 *
	 * @access	public
	 * @return	mixed JSON or Boolean for validity
	 */
	public function validate_toolset_name()
	{
		return $this->EE->rte_lib->validate_toolset_name();
	}

	// --------------------------------------------------------------------
	
	/**
	 * Enables a tool based on the rte_tool_id passed in
	 *
	 * @access	public
	 * @return	void
	 */
	public function enable_tool()
	{
		$this->_update_tool(
			$this->EE->input->get_post('rte_tool_id'),
			array( 'enabled' => 'y' ),
			lang('tool_enabled'),
			lang('tool_update_failed')
		);
	}

	// --------------------------------------------------------------------
	
	/**
	 * 	Disables a tool based on the rte_tool_id passed in
	 *
	 * @access	public
	 * @return	void
	 */
	public function disable_tool()
	{
		$this->_update_tool(
			$this->EE->input->get_post('rte_tool_id'),
			array( 'enabled' => 'n' ),
			lang('tool_disabled'),
			lang('tool_update_failed')
		);
	}

	// --------------------------------------------------------------------
	
	/**
	 * MyAccount RTE settings form action
	 *
	 * @access	public
	 * @return	int The number of affected rows (should be 1 or 0)
	 */
	public function toggle_member_rte()
	{
		// get the current status
		$enabled = ($this->EE->session->userdata('rte_enabled') == 'y');
		
		// update the prefs
		$this->EE->db->update(
			'members',
			array( 'rte_enabled'	=> ($enabled ? 'n' : 'y') ),
			array( 'member_id'		=> $this->EE->session->userdata('member_id') )
		);
		
		// exit
		$affected_rows = $this->EE->db->affected_rows();
		if ($this->EE->input->is_ajax_request())
		{
			die( $affected_rows );
		}
		else
		{
			return $affected_rows;
		}
	}

	// --------------------------------------------------------------------
	
	/**
	 * Build the toolset JS
	 *
	 * @access	public
	 * @param	int $toolset_id The ID of the toolset to load
	 * @return	string The JavaScript
	 */
	public function build_toolset_js( $toolset_id = FALSE )
	{
		$this->EE->load->library('javascript');
		
		// load in the event information so buttons can trigger 
		$this->EE->javascript->set_global( 'rte.update_event', 'WysiHat-editor:change' );
		
		// start empty
		$js = '';

		// determine the toolset
		$this->EE->load->model(array('rte_toolset_model','rte_tool_model'));

		if ( ! $toolset_id)
		{
			$toolset_id = $this->EE->rte_toolset_model->get_member_toolset();
		}

		$tools = $this->EE->rte_tool_model->get_tools($toolset_id);

		// make sure we should load the JS
		if ( ! $tools OR $this->EE->config->item('rte_enabled') != 'y')
		{
			return;
		}
		
		// setup the framework
		ob_start(); ?>

		$(".rte").each(function(){
			var
			$field	= $(this),
			$parent	= $field.parent(),

			// set up the editor
			$editor	= WysiHat.Editor.attach($field),

			// establish the toolbar
			toolbar	= new WysiHat.Toolbar();

			toolbar.initialize($editor);

<?php	$js = ob_get_contents();
		ob_end_clean(); 

		// load the tools
		foreach ($tools as $tool)
		{
			// load the globals
			if (count($tool['globals']))
			{
				$this->EE->javascript->set_global( $tool['globals'] );
			}
			
			// load any libraries we need
			if (count($tool['libraries']))
			{
				$this->EE->cp->add_js_script( $tool['libraries'] );
			}
			
			// add any styles we need
			if ( ! empty( $tool['styles']))
			{
				$this->EE->cp->add_to_head( '<style>' . $tool['styles'] . '</style>' );
			}
			
			// load in the definition
			if ( ! empty( $tool['definition']))
			{
				$js .= $tool['definition'];
			}
		}

		$js .= '

			});
			';
		
		// return vs. print… is there a better CI way to do this?
		$print = $this->EE->input->get_post('print');

		if ($print == 'yes')
		{
			header('Content-type: text/javascript; charset=utf-8');
			header('Cache-Control: no-cache, must-revalidate');
			header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
			
			// compile the JS
			$this->EE->javascript->compile();

			die('
				(function(){
					var EE = ' . $this->EE->javascript->generate_json($this->EE->javascript->global_vars) . ';' .
				 	$js .
				'})();
				');
		}
		else
		{
			return $js;
		}
	}

	// --------------------------------------------------------------------

	/**
	 * RTE toggle JS
	 *
	 * @access	public
	 * @return	string The JavaScript
	 */
	public function build_rte_toggle_js()
	{
		$js = '';
		
		// make sure it’s on
		if ($this->EE->config->item('rte_enabled') == 'y')
		{
			// styles
			$this->EE->cp->add_to_head(
				'
				<style>
					.rte_toggle_link { display:block; float: right; margin: 5px 30px 5px 0; }
					#rte_toggle_dialog p { margin: 10px 0; }
					#rte_toggle_dialog .buttons { text-align: center; }
				</style>
				'
			);
			
			// JS config
			$this->EE->javascript->set_global(array(
				'rte'	=> array(
					'update_event'		=> 'WysiHat-editor:change',
					'toolset_src'		=> $this->_base_url.AMP.'method=build_toolset_js'.AMP.'print=yes',
					'toggle_rte_url'	=> $this->_base_url.AMP.'method=toggle_member_rte',
					'is_enabled'		=> ($this->EE->session->userdata('rte_enabled') == 'y'),
					'toggle_dialog'		=> array(
						'title'				=> lang('toggle_rte_dialog_title'),
						'headline_disable'	=> lang('toggle_rte_dialog_headline_disable'),
						'headline_enable'	=> lang('toggle_rte_dialog_headline_enable'),
						'text_disable'		=> lang('toggle_rte_dialog_text_disable'),
						'text_enable'		=> lang('toggle_rte_dialog_text_enable'),
						'disable'			=> lang('disable_button'),
						'enable'			=> lang('enable_button'),
						'cancel'			=> lang('cancel')
					),
					'toggle_link'		=> array(
						'text_disable'	=> lang('disable_rte'),
						'text_enable'	=> lang('enable_rte')
					)
				)
			));
			
			// add in the code that would toggle the toolset
			ob_start(); ?>
			
			var
			$rte_toggle_link	= $( '<a class="rte_toggle_link" href="#rte_toggle_dialog"></a>' ),
			$rte_toggle_dialog 	= $( '<div id="rte_toggle_dialog">' +
										'<p class="headline"><strong></strong></p><p></p><p class="buttons">' +
										'<button value="yes" class="submit"></button> or <a href="#cancel"></a></p>' +
									 '</div>' );
		
			function setup_rte_toggle_dialog()
			{
				var
				link	= EE.rte.is_enabled ? EE.rte.toggle_link.text_disable : EE.rte.toggle_link.text_enable,
				head	= EE.rte.is_enabled ? EE.rte.toggle_dialog.headline_disable : EE.rte.toggle_dialog.headline_enable,
				text	= EE.rte.is_enabled ? EE.rte.toggle_dialog.text_disable : EE.rte.toggle_dialog.text_enable,
				yes		= EE.rte.is_enabled ? EE.rte.toggle_dialog.disable : EE.rte.toggle_dialog.enable;
			
				$('.rte_toggle_link')
					.text(link);
				$rte_toggle_dialog
					.find('strong').text(head).end()
					.find('p:not([class])').text(text).end()
					.find('button').text(yes).end();
			}
		
			function toggle_rte()
			{
				var re_amp = /&amp;/g;
				$.get( EE.rte.toggle_rte_url.replace(re_amp,'&'), function(){
					if ( EE.rte.is_enabled )
					{
						$('[class|=WysiHat]').remove();
						$('.rte').show();
					}
					else
					{
						$.getScript( EE.rte.toolset_src.replace(re_amp,'&') );
					}				
				
					// toggle the status and the dialog
					EE.rte.is_enabled = ! EE.rte.is_enabled;
				
					$rte_toggle_dialog.dialog('close');
					setup_rte_toggle_dialog();
				});
			}
			
			// set up the link
			$rte_toggle_link
				.click(function(e){
					e.preventDefault();
					$rte_toggle_dialog.dialog('open');
				 });
		
			// insert it
			$(".rte").each(function(){
				$rte_toggle_link
					.clone(true)
					.insertAfter($(this));
			});

			// run setup once
			setup_rte_toggle_dialog();

			// set up the Dialog box
			$rte_toggle_dialog
				.dialog({
					width: 400,
					height: 180,
					resizable: false,
					position: ["center","center"],
					modal: true,
					draggable: true,
					title: EE.rte.toggle_dialog.title,
					autoOpen: false,
					zIndex: 99999
				 })
				.find('.buttons button')
					.click(toggle_rte)
					.end()
				.find('.buttons a')
					.text( EE.rte.toggle_dialog.cancel )
					.click(function(e){
						e.preventDefault();
						$rte_toggle_dialog.dialog('close');
					});
		
<?php		$js = ob_get_contents();
			ob_end_clean(); 
		}

		return $js;
	}

	// --------------------------------------------------------------------

	/**
	 * Actual preference-updating code
	 * 
	 * @access	private
	 * @return	void
	 */
	private function _do_update_prefs()
	{
		// update the config
		$this->EE->config->_update_config(array(
			'rte_enabled'				=> $this->EE->input->get_post('rte_enabled'),
			'rte_default_toolset_id'	=> $this->EE->input->get_post('rte_default_toolset_id')
		));
	}

	// --------------------------------------------------------------------
	
	/**
	 * Update the tool
	 *
	 * @access	private
	 * @return	void
	 */
	private function _update_tool( $tool_id = 0, $change = array(), $success_msg, $fail_msg )
	{
		$this->EE->load->model('rte_tool_model');
		
		// save
		if ($this->EE->rte_tool_model->save($change, $tool_id))
		{
			$this->EE->session->set_flashdata('message_success', $success_msg);
		}
		else
		{
			$this->EE->session->set_flashdata('message_failure', $fail_msg);
		}

		$this->EE->functions->redirect($this->_base_url);
	}

	// --------------------------------------------------------------------

	/**
	 * Makes sure users can access a given method
	 * 
	 * @access	private
	 * @return	void
	 */
	private function _permissions_check()
	{
		// super admins always can
		$can_access = ($this->EE->session->userdata('group_id') == '1');
		
		if ( ! $can_access)
		{
			// get the group_ids with access
			$result = $this->EE->db->select('module_member_groups.group_id')
				->from('module_member_groups')
				->join('modules', 'modules.module_id = module_member_groups.module_id')
				->where('modules.module_name',$this->name)
				->get();

			if ($result->num_rows())
			{
				foreach ($result->result_array() as $r)
				{
					if ($this->EE->session->userdata('group_id') == $r['group_id'])
					{
						$can_access = TRUE;
						break;
					}
				}
			}
		}
		
		if ( ! $can_access)
		{
			show_error(lang('unauthorized_access'));
		}		
	}
	
}
// END CLASS

/* End of file mcp.rte.php */
/* Location: ./system/expressionengine/modules/rte/mcp.rte.php */