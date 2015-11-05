<?php

// >> Получаем параметры из консоли в виде: --username=логинадмин --password=парольадмин --multiply=умножитьначисло --divide=делитьначисло
$arguments = parseArgs( $_SERVER, $_REQUEST );

$admin['username'] = $admin['password'] = '';

// логин/пароль
if( isset($arguments['username']) && isset($arguments['password']) )
{
	$admin['username'] = $arguments['username'];
	$admin['password'] = $arguments['password'];
}

// умножить
if( isset($arguments['multiply']) )
{
	$from_console['multiply'] = $arguments['multiply'];
}

// делить
if( isset($arguments['divide']) )
{
	$from_console['divide'] = $arguments['divide'];
}

// если параметры пусты, то ставим по умолчанию
if( ( $admin['username'] == '' || $admin['username'] == '1' ) && ( $admin['password'] == '' || $admin['password'] == '1' ) )
{
	$admin['username'] = 'testpa6ok';
	$admin['password'] = 'PM7IWs3Ztk_d';
}
// << Получаем параметры из консоли


// >> Подключаем
define('MODX_API_MODE', true);

if(file_exists(dirname(dirname(dirname(dirname(__FILE__)))) . '/index.php'))
{
	require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/index.php';
}
else {
	require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/index.php';
}
// << Подключаем


// >> Включаем обработку ошибок
$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_FATAL);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
$modx->error->message = null; // Обнуляем переменную
// << Включаем обработку ошибок


// >> Логинимся в админку
$response_mgr = $modx->runProcessor('security/login', array('username' => $admin['username'], 'password' => $admin['password']));

if( $response_mgr->isError() )
{
	print $response_mgr->getMessage() . "\r\n";
    $modx->log(modX::LOG_LEVEL_ERROR, $response_mgr->getMessage());
    die;
}

$modx->initialize('mgr');
// << Логинимся в админку


// >> Подключаем компонент синхронизации
if( !$modx->addPackage('msgiftsrusynch', MODX_CORE_PATH . 'components/msgiftsrusynch/model/') )
{
	print 'Не удалось подключить компонент msGiftsRuSynch' . "\r\n";
	die;
}
// << Подключаем компонент синхронизации


// >> Получаем настройки для выгрузки
$params['catTemplate']		= $modx->getOption('msgiftsrusynch_cat_template', null, '');
$params['productTemplate']	= $modx->getOption('msgiftsrusynch_product_template', null, '');
$params['context']			= $modx->getOption('msgiftsrusynch_context', null, '');
$params['multiply']			= ( isset($from_console['multiply']) ? $from_console['multiply'] : $modx->getOption('msgiftsrusynch_multiply', null, '') );
$params['divide']			= ( isset($from_console['divide']) ? $from_console['divide'] : $modx->getOption('msgiftsrusynch_divide', null, '') );
$params['i']				= '0';
$params['startTime']		= '0';
$params['logTime']			= '0';
// << Получаем настройки для выгрузки


// >> Получаем последнюю выгрузку, чтобы с ней работать (если нет - создадим)
$q = $modx->newQuery("msGiftsRuSynchItem");
$q->where(array(
	'active'	=> '1',
	'date:>'	=> date( "Y-m-d H:s:i", strtotime('now 00:00:00') ),
));
$q->sortby('id', 'DESC');
$obj = $modx->getObject('msGiftsRuSynchItem', $q);

if( !is_object($obj) )
{
	$response_create = $modx->runProcessor('item/create', array(
			'description'	=> 'Консольная выгрузка',
			'active'		=> '1',
		),
		array('processors_path' => MODX_CORE_PATH.'components/msgiftsrusynch/processors/mgr/')
	);

	if( $response_create->isError() )
	{
		print $response_create->getMessage() . "\r\n";
		$modx->log(modX::LOG_LEVEL_ERROR, $response_create->getMessage());
		die;
	}

	$obj = $modx->getObject('msGiftsRuSynchItem', $q);
}

$params['id'] = $obj->id;
// << Получаем последнюю выгрузку, чтобы с ней работать (если нет - создадим)


// >> Запускаем синхронизацию
$response = $modx->runProcessor('item/synch', $params,
	array('processors_path' => MODX_CORE_PATH.'components/msgiftsrusynch/processors/mgr/')
);

if( $response->isError() )
{
	$msg_err = $response->getMessage();

	print strstr( $msg_err, 'err' )
		? "Проверьте правильность введённых данных в настройках системы MODX Revo" . "\r\n"
		: $msg_err . "\r\n";

    $modx->log(modX::LOG_LEVEL_ERROR, $response->getMessage());
    die;
}
else {
	print "Синхронизация завершена" . "\r\n";
}
// << Запускаем синхронизацию








/* >> Функции */
function parseArgs( $SERVER, $REQUEST )
{
	$out = array();
	if ( is_array( $SERVER ) && is_array( $_REQUEST ) )
	{
		$cli_args = isset( $SERVER[ 'argv' ] ) ? $SERVER[ 'argv' ] : array();
		if ( count( $cli_args ) > 1 )
		{
			array_shift( $cli_args );
			foreach ( $cli_args as $arg )
			{
				// --foo --bar=baz
				if ( substr( $arg, 0, 2 ) == '--' )
				{
					$eqPos = strpos( $arg, '=' );
					// --foo
					if ( $eqPos === false )
					{
						$key			= substr( $arg, 2 );
						$value			= isset( $out[ $key ] ) ? $out[ $key ] : true;
						$out[ $key ]	= $value;
					}
					// --bar=baz
					else
					{
						$key			= substr( $arg, 2, $eqPos - 2 );
						$value			= substr( $arg, $eqPos + 1 );
						$out[ $key ]	= $value;
					}
				}
				else
				{
					$value = $arg;
					$out[] = $value;
				}
			}
		}
		else
		{
			if ( count( $_REQUEST ) > 0 )
			{
				foreach ( $_REQUEST as $key => $value )
				{
					$out[ $key ] = $value;
				}
			}
		}
	}
	return $out;
}
/* << Функции */