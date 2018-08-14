<?php
	/**
	 * Plugin Name:       Gravity Forms GDPR Add-On
	 * Plugin URI:        https://www.creuna.fi
	 * Description:       Adds features for easier GDPR compliance with Gravity Forms.
	 * Version:           1.0.0
	 * Author:            Creuna Finland Oy Ab
	 * Author URI:        https://www.creuna.fi
	 * License:           GPL-2.0+
	 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
	 * Text Domain:       gf-gdpr-addon
	 * Contributors:      Ville Huumo, Tomi Mäenpää
	 */

	define('GF_GDPR_ADDON_VERSION', '1.0');

	add_action('gform_loaded', ['GF_GDPR_AddOn_Bootstrap', 'load'], 5);


	/**
	 * Bootstrap Gravity Forms Add-On
	 * Class GF_GDPR_AddOn_Bootstrap
	 * @see https://docs.gravityforms.com/add-on-framework/
	 * @since 1.0.0
	 */
	class GF_GDPR_AddOn_Bootstrap
	{

		public static function load()
		{

			if (!method_exists('GFForms', 'include_addon_framework')) {
				return;
			}

			require_once('class-gf-gdpr-addon.php');

			GFAddOn::register('GF_GDPR_AddOn');
		}

	}


	/**
	 * Get singleton instance
	 * @return GF_GDPR_AddOn
	 * @see https://docs.gravityforms.com/add-on-framework/
	 * @since 1.0.0
	 */
	function gf_gdpr_addon()
	{
		return GF_GDPR_AddOn::get_instance();
	}