<?php
/**
 * Migration 001: Create tables
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = new PSP_Territorial_Database();
$db->create_tables();
