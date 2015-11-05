<?php

$settings = array();

$tmp = array(/*
	'some_setting' => array(
		'xtype' => 'combo-boolean',
		'value' => true,
		'area' => 'msgiftsrusynch_main',
	),
	*/

	'api_user' => array(
		'xtype' => 'textfield',
		'value' => '',
		'area' => 'msgiftsrusynch_api',
	),
	'api_password' => array(
		'xtype' => 'textfield',
		'value' => '',
		'area' => 'msgiftsrusynch_api',
	),

	'cat_template' => array(
		'xtype' => 'modx-combo-template',
		'value' => '',
		'area' => 'msgiftsrusynch_synch',
	),
	'product_template' => array(
		'xtype' => 'modx-combo-template',
		'value' => '',
		'area' => 'msgiftsrusynch_synch',
	),
	'context' => array(
		'xtype' => 'modx-combo-context',
		'value' => 'web',
		'area' => 'msgiftsrusynch_synch',
	),

	'multiply' => array(
		'xtype' => 'numberfield',
		'value' => '',
		'area' => 'msgiftsrusynch_price',
	),
	'divide' => array(
		'xtype' => 'numberfield',
		'value' => '',
		'area' => 'msgiftsrusynch_price',
	),
);

foreach ($tmp as $k => $v) {
	/* @var modSystemSetting $setting */
	$setting = $modx->newObject('modSystemSetting');
	$setting->fromArray(array_merge(
		array(
			'key' => 'msgiftsrusynch_' . $k,
			'namespace' => PKG_NAME_LOWER,
		), $v
	), '', true, true);

	$settings[] = $setting;
}

unset($tmp);
return $settings;
