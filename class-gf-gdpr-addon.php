<?php


	/**
	 * Class GF_GDPR_AddOn
	 * @since Version 1.0.0
	 */
	class GF_GDPR_AddOn extends GFAddOn
	{

		private static $_instance = null;
		protected $_version = GF_GDPR_ADDON_VERSION;
		protected $_min_gravityforms_version = '1.9';
		protected $_slug = 'gf-gdpr-addon';
		protected $_path = 'gravity-forms-gdpr-addon/gravity-forms-gdpr.php';
		protected $_full_path = __FILE__;
		protected $_title = 'Gravity Forms GDPR Add-On';
		protected $_short_title = 'GDPR Add-On';

		/**
		 * Get singleton
		 * @return GF_GDPR_AddOn
		 * @since Version 1.0.0
		 */
		public static function get_instance()
		{
			if (self::$_instance == null) {
				self::$_instance = new GF_GDPR_AddOn();
			}

			return self::$_instance;
		}

		/**
		 * Register needed pre-initialization hooks.
		 * @since Version 1.0.0
		 */
		public function pre_init()
		{
			/**
			 * Delete expired forms and entries.
			 * @since Version 1.0.0
			 */
			add_action('gf_gdpr_addon_maybe_expire', [$this, 'maybe_expire']);
			add_filter('gform_ip_address', [$this, 'maybe_disable_ip_saving'], 10, 1);
			add_filter('gform_pre_render', [$this, 'maybe_add_privacy_policy_class'], 10, 1);
			add_filter('gform_submit_button', [$this, 'maybe_add_privacy_policy_text'], 20, 1);

			if (!wp_next_scheduled('gf_gdpr_addon_maybe_expire')) {
				$scheduled = wp_schedule_event(time(), apply_filters('gf_gdpr_addon_recurrence', 'hourly'),
					'gf_gdpr_addon_maybe_expire');
			}
		}

		/**
		 * Filter for disabling IP-address saving
		 *
		 * @param $status
		 *
		 * @return bool
		 * @since Version 1.0.0
		 */
		public function maybe_disable_ip_saving($status)
		{
			$setting = $this->get_plugin_setting('ip_saving_disabled_enable');

			return !empty($setting) ? false : $status;
		}

		/**
		 * Filter for adding Privacy Policy link after the form button
		 *
		 * @param $button
		 *
		 * @return string
		 * @since Version 1.0.0
		 */
		public function maybe_add_privacy_policy_text($button)
		{
			$setting = $this->get_plugin_setting('privacy_policy_enabled_enable');
			$id      = get_option('wp_page_for_privacy_policy');

			/**
			 * Filter the Privacy Policy page Id.
			 *
			 * @param int $id
			 *
			 * @since Version 1.0.0
			 */
			$page = apply_filters('gf_gdpr_addon_privacy_policy_page', $id);

			if (!empty($setting) && !empty($page)) {

				$html = sprintf('<p class="gf-gdpr-addon-privacy-policy-section"><a href="%s">%s</a></p>',
					get_the_permalink($page),
					__('Privacy Policy', 'gf-gdpr-addon'));

				/**
				 * Filter Privacy Policy html appended after the Form.
				 *
				 * @param string $html
				 *
				 * @since Version 1.0.0
				 */
				$html   = apply_filters('gf_gdpr_addon_privacy_policy_html', $html);
				$button .= $html;
			}

			return $button;
		}

		/**
		 * Filter for appending Privacy Policy CSS-class to the Form classes
		 *
		 * @param $form
		 *
		 * @return mixed
		 * @since Version 1.0.0
		 */
		public function maybe_add_privacy_policy_class($form)
		{
			$setting = $this->get_plugin_setting('privacy_policy_enabled_enable');
			$id      = get_option('wp_page_for_privacy_policy');

			/**
			 * Filter the Privacy Policy page Id.
			 *
			 * @param int $id
			 *
			 * @since Version 1.0.0
			 */
			$page = apply_filters('gf_gdpr_addon_privacy_policy_page', $id);

			if (!empty($setting) && !empty($page)) {
				$form['cssClass'] .= ' gf-gdpr-addon-has-privacy-policy-link';
			}

			return $form;
		}

		/**
		 * The Cron-job
		 * @since Version 1.0.0
		 */
		public function maybe_expire()
		{
			$this->maybe_expire_entries();
			$this->maybe_expire_forms();
		}

		/**
		 * Expire Gravity Forms entries.
		 * @since Version 1.0.0
		 */
		public function maybe_expire_entries()
		{
			$begin_date = new DateTime();
			$begin_date->setTimestamp(0);

			$expiry_setting = $this->get_plugin_setting('form_expiration_duration');
			$expiry         = '-' . $expiry_setting['number'] . ' ' . $expiry_setting['unit'];
			$expiry_date    = new DateTime();
			$expiry_date->setTimezone($this->get_tz());
			$expiry_date->modify($expiry);

			$search_criteria = [
				'start_date' => $begin_date->format('Y-m-d H:i:s'),
				'end_date'   => $expiry_date->format('Y-m-d H:i:s'),
			];

			$entries = GFAPI::get_entries(0, $search_criteria);

			if (empty($entries)) {
				return;
			}

			foreach ($entries as $entry) {
				GFAPI::delete_entry($entry['id']);
			}
		}

		/**
		 * Get blog timezone
		 * @return DateTimeZone
		 * @link https://wordpress.stackexchange.com/questions/198435/how-to-convert-datetime-to-display-time-based-on-wordpress-timezone-setting
		 * @since Version 1.0.0
		 */
		public function get_tz()
		{
			$tzstring = get_option('timezone_string');
			$offset   = get_option('gmt_offset');

			//Manual offset...
			//@see http://us.php.net/manual/en/timezones.others.php
			//@see https://bugs.php.net/bug.php?id=45543
			//@see https://bugs.php.net/bug.php?id=45528
			//IANA timezone database that provides PHP's timezone support uses POSIX (i.e. reversed) style signs
			if (empty($tzstring) && 0 != $offset && floor($offset) == $offset) {
				$offset_st = $offset > 0 ? "-$offset" : '+' . absint($offset);
				$tzstring  = 'Etc/GMT' . $offset_st;
			}

			//Issue with the timezone selected, set to 'UTC'
			if (empty($tzstring)) {
				$tzstring = 'UTC';
			}

			$timezone = new DateTimeZone($tzstring);

			return $timezone;
		}

		/**
		 * Expire Gravity Forms forms.
		 * @since Version 1.0.0
		 */
		public function maybe_expire_forms()
		{
			$forms = [];
			$forms = array_merge($forms, GFAPI::get_forms(true)); // Get active forms
			$forms = array_merge($forms, GFAPI::get_forms(false)); // Get inactive forms
			$forms = array_merge($forms, GFAPI::get_forms(true, true)); // Get active forms in trash
			$forms = array_merge($forms, GFAPI::get_forms(false, true)); // Get inactive forms in trash

			// Filter out forms that have been opted out of expiry
			foreach ($forms as $key => $form) {
				$settings = $this->get_form_settings($form);
				if (!empty($settings['form_expiry_opt_out'])) {
					unset($forms[$key]);
					continue;
				}
			}

			if (empty($forms)) {
				return;
			}

			$expiry_setting = $this->get_plugin_setting('form_expiration_duration');
			$expiry         = '-' . $expiry_setting['number'] . ' ' . $expiry_setting['unit'];
			$expiry_date    = new DateTime();
			$expiry_date->setTimezone($this->get_tz());
			$expiry_date->modify($expiry);

			// Filter out forms where the form creation date has not yet passed the expiry date
			foreach ($forms as $key => $form) {
				$form_date = new DateTime($form['date_created']);

				if ($form_date > $expiry_date) {
					unset($forms[$key]);
					continue;
				}
			}

			if (empty($forms)) {
				return;
			}

			GFAPI::delete_forms(wp_list_pluck($forms, 'id'));
		}

		/**
		 * Plugin settings fields
		 * @return array
		 * @since Version 1.0.0
		 */
		public function plugin_settings_fields()
		{
			return [
				[
					'title'  => esc_html__('Entry Expiration', 'gf-gdpr-addon'),
					'fields' => [
						[
							'name'    => 'entry_expiration_enabled',
							'label'   => esc_html__('Enable', 'gf-gdpr-addon'),
							'type'    => 'checkbox',
							'onclick' => "jQuery( this ).parents( 'form' ).submit()",
							'choices' => [
								[
									'name'  => 'entry_expiration_enabled_enable',
									'label' => esc_html__('Automatically delete form entries on a defined schedule',
										'gf-gdpr-addon'),
								],
							],
						],
						[
							'name'       => 'entry_expiration_duration',
							'label'      => esc_html__('Delete entries older than', 'gf-gdpr-addon'),
							'type'       => 'text_select',
							'required'   => true,
							'dependency' => ['field' => 'entry_expiration_enabled_enable', 'values' => ['1']],
							'text'       => [
								'name'          => 'entry_expiration_enabled[number]',
								'class'         => 'small',
								'input_type'    => 'number',
								'default_value' => '3',
								'after_input'   => ' ',
							],
							'select'     => [
								'name'          => 'entry_expiration_enabled[unit]',
								'default_value' => 'months',
								'choices'       => [
									[
										'label' => 'hours',
										'value' => esc_html__('hours', 'gf-gdpr-addon')
									],
									[
										'label' => 'days',
										'value' => esc_html__('days', 'gf-gdpr-addon')
									],
									[
										'label' => 'weeks',
										'value' => esc_html__('weeks', 'gf-gdpr-addon')
									],
									[
										'label' => 'months',
										'value' => esc_html__('months', 'gf-gdpr-addon')
									],
									[
										'label' => 'years',
										'value' => esc_html__('years', 'gf-gdpr-addon')
									],
								],
							],
						],

					]
				],
				[
					'title'  => esc_html__('Form Expiration', 'gf-gdpr-addon'),
					'fields' => [

						[
							'name'    => 'form_expiration_enabled',
							'label'   => esc_html__('Enable', 'gf-gdpr-addon'),
							'type'    => 'checkbox',
							'onclick' => "jQuery( this ).parents( 'form' ).submit()",
							'choices' => [
								[
									'name'  => 'form_expiration_enabled_enable',
									'label' => esc_html__('Automatically delete forms on a defined schedule',
										'gf-gdpr-addon'),
								],
							],
						],

						[
							'name'       => 'form_expiration_duration',
							'label'      => esc_html__('Delete forms older than', 'gf-gdpr-addon'),
							'type'       => 'text_select',
							'required'   => true,
							'dependency' => ['field' => 'form_expiration_enabled_enable', 'values' => ['1']],
							'text'       => [
								'name'          => 'form_expiration_duration[number]',
								'class'         => 'small',
								'input_type'    => 'number',
								'default_value' => '12',
								'after_input'   => ' ',
							],
							'select'     => [
								'name'          => 'form_expiration_duration[unit]',
								'default_value' => 'months',
								'choices'       => [
									[
										'label' => 'hours',
										'value' => esc_html__('hours', 'gf-gdpr-addon')
									],
									[
										'label' => 'days',
										'value' => esc_html__('days', 'gf-gdpr-addon')
									],
									[
										'label' => 'weeks',
										'value' => esc_html__('weeks', 'gf-gdpr-addon')
									],
									[
										'label' => 'months',
										'value' => esc_html__('months', 'gf-gdpr-addon')
									],
									[
										'label' => 'years',
										'value' => esc_html__('years', 'gf-gdpr-addon')
									],
								],
							],
						]

					]
				],
				[
					'title'  => esc_html__('Privacy Policy', 'gf-gdpr-addon'),
					'fields' => [

						[
							'name'    => 'privacy_policy_enabled',
							'label'   => esc_html__('Enabled', 'gf-gdpr-addon'),
							'type'    => 'checkbox',
							'choices' => [
								[
									'name'  => 'privacy_policy_enabled_enable',
									'label' => esc_html__('Add privacy policy to every form',
										'gf-gdpr-addon'),
								],
							],
						],
					]
				],
				[
					'title'  => esc_html__('Misc', 'gf-gdpr-addon'),
					'fields' => [

						[
							'name'    => 'ip_saving_disabled',
							'label'   => esc_html__('Enabled', 'gf-gdpr-addon'),
							'type'    => 'checkbox',
							'choices' => [
								[
									'name'  => 'ip_saving_disabled_enable',
									'label' => esc_html__('Disable saving of IP-addresses for form entries',
										'gf-gdpr-addon'),
								],
							],
						],
					]
				]
			];
		}

		/**
		 * Form setting fields
		 *
		 * @param $form
		 *
		 * @return array
		 * @since Version 1.0.0
		 */
		public function form_settings_fields($form)
		{
			return [
				[
					'title'  => esc_html__('Form Expiration', 'gf-gdpr-addon'),
					'fields' => [
						[
							'label'   => esc_html__('Opt-out', 'gf-gdpr-addon'),
							'type'    => 'checkbox',
							'tooltip' => esc_html__('With this setting you may opt-out from the expiry of the form but the entries will still be removed after the time has passed.',
								'gf-gdpr-addon'),
							'name'    => 'Opt-out of form expiry',
							'choices' => [
								[
									'label' => esc_html__('Keep this form', 'simpleaddon'),
									'name'  => 'form_expiry_opt_out',
								],
							]
						]
					]
				]
			];
		}

		/**
		 * Validate custom input settings
		 *
		 * @param $field
		 * @param $settings
		 *
		 * @link http://travislop.es/plugins/gravity-forms-entry-expiration/
		 * @author Travis Lopes
		 * @since Version 1.0.0
		 */
		public function validate_text_select_settings($field, $settings)
		{

			// Convert text field name.
			$text_field_name = str_replace(['[', ']'], ['/', ''], $field['text']['name']);

			// Get text field value.
			$text_field_value = rgars($settings, $text_field_name);

			// If text field is empty and field is required, set error.
			if (rgblank($text_field_value) && rgar($field, 'required')) {
				$this->set_field_error($field, esc_html__('This field is required.', 'gf-gdpr-addon'));

				return;
			}

			// If text field is not numeric, set error.
			if (!rgblank($text_field_value) && !ctype_digit($text_field_value)) {
				$this->set_field_error($field,
					esc_html__('You must use a whole number.', 'gf-gdpr-addon'));

				return;
			}
		}

		/**
		 * Custom Form element with text and dropdown
		 *
		 * @param $field
		 * @param bool $echo
		 *
		 * @return string
		 * @link http://travislop.es/plugins/gravity-forms-entry-expiration/
		 * @author Travis Lopes
		 * @since Version 1.0.0
		 */
		public function settings_text_select($field, $echo = true)
		{

			// Initialize return HTML.
			$html = '';

			// Duplicate fields.
			$select_field = $text_field = $field;

			// Merge properties.
			$text_field   = array_merge($text_field, $text_field['text']);
			$select_field = array_merge($select_field, $select_field['select']);

			unset($text_field['text'], $select_field['text'], $text_field['select'], $select_field['select']);

			$html .= $this->settings_text($text_field, false);
			$html .= $this->settings_select($select_field, false);

			if ($this->field_failed_validation($field)) {
				$html .= $this->get_error_icon($field);
			}

			if ($echo) {
				echo $html;
			}

			return $html;
		}

	}
