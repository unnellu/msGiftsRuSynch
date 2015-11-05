<?php

/**
 * Synchronization xml gifts.ru with minishop2
 */
class msGiftsRuSynchItemSynchronizationProcessor extends modObjectProcessor
{
	public $objectType = 'msGiftsRuSynchItem';
	public $classKey = 'msGiftsRuSynchItem';
	public $languageTopics = array('msgiftsrusynch');
	//public $permission = 'synch';

	private $assetsPath = '';

	// >> статистика (недоработанная)
	private $all_categories_i = 0;
	private $new_categories_i = 0;
	private $edit_categories_i = 0;
	private $remove_categories_i = 0;

	private $all_options_i = 0;
	private $new_options_i = 0;
	private $new_options_values_i = 0;
	private $edit_options_i = 0;

	private $all_products_i = 0;
	private $new_products_i = 0;
	private $edit_products_i = 0;
	private $remove_products_i = 0;
	// << статистика (недоработанная)

	private $xmlFiles = array();

	// >> массивы с данными для работы
	private $array_categories = array();
	private $array_options = array();
	private $array_stock_products = array();
	private $array_products_categories = array();
	// << массивы с данными для работы

	private $url_gifts_api = '';

	private $obj = 0; // объект выгрузки
	private $msop2_option_size_id = 0; // id msop2 связи по размеру

	private $color_link_id = 0; // id связи по цвету

	private $start_time = 0; // время старта
	private $restart_time = 0; // время рестарта
	private $max_exec_time = 0; // максимальное время для php скрипта
	private $now_time = 0; // текущее время

	private $restartScript = 1; // рестартить скрипт? (нет - если запустили из консоли)

	private $iteration = 0; // номер итерации
	private $logTime = 0; // имя файла лога

	private $img_count_load = 0; // счёт загруженных изображений за секунду (для обхода ограничения на загрузку 5 файлов за секунду)
	private $img_count_load_time = 0; // время загрузки последнего изображения (для обхода ограничения на загрузку 5 файлов за секунду)



	public function initialize()
	{
		if( !$this->checkPermissions() ) {
			return $this->modx->lexicon('access_denied');
		}


		$this->xmlFiles = array(
			'treeWithoutProducts.xml', // * каталоги без привязки к товарам
			'stock.xml', // * кол-во товаров на складе
			'filters.xml', // * доп поля
			'catalogue.xml', // * товары + привязка к каталогам
		);


		$this->assetsPath = $this->modx->getOption('msgiftsrusynch_assets_path', null, $this->modx->getOption('assets_path') . 'components/msgiftsrusynch/');

		$this->iteration = $this->getProperty('i'); // номер итерации по массиву продуктов
		$this->logTime = $this->getProperty('logTime'); // имя лог-файла

		$this->start_time = $this->getProperty('startTime'); // время старта скрипта
		$this->start_time = ( !$this->start_time ? explode(" ", microtime())[1] : $this->start_time ); // время старта скрипта

		$this->restart_time = explode(" ", microtime())[1]; // время перезапуска скрипта
		$this->max_exec_time = ( ini_get("max_execution_time") < 30 ? ini_get("max_execution_time") : 30 ); // максимальное время выполнения php скриптов из браузера


		// >> если max_execution_time = 0, то скрипт не перезапускать
		if( ini_get("max_execution_time") == '0' )
		{
			$this->restartScript = 0;
		}
		// << если max_execution_time = 0, то скрипт не перезапускать


		// >> данные для доступа к АПИ
		$api_user = $this->modx->getOption('msgiftsrusynch_api_user', null, ''); // 20865_xmlexport
		$api_password = $this->modx->getOption('msgiftsrusynch_api_password', null, ''); // 123456

		if( $api_user == '' || $api_password == '' )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_api_login');
		}

		$this->url_gifts_api = "http://".$api_user.":".$api_password."@api2.gifts.ru/export/v2/catalogue/";

		if( $this->iteration == '0' )
		{
			$api_page_content = file_get_contents( $this->url_gifts_api . $this->xmlFiles[0] );

			if( strstr( $api_page_content, '<title>401</title>' ) || strstr( $api_page_content, 'You can change' ) )
			{
				return $this->modx->lexicon('msgiftsrusynch_item_err_api_ip');
			}
		}
		// << данные для доступа к АПИ


		/* >> Подключаем minishop2 и msop2 */
		if( !$this->modx->addPackage('minishop2', MODX_CORE_PATH . 'components/minishop2/model/') )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_ms2');
		}

		if( !$this->modx->addPackage('msop2', MODX_CORE_PATH . 'components/msop2/model/') )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_msop2');
		}
		/* << Подключаем minishop2 и msop2 */


		/*if( !is_object($this->modx->msgiftsrusynch) )
		{
			$this->modx->getService('msgiftsrusynch', 'msgiftsrusynch', MODX_CORE_PATH . 'components/msgiftsrusynch/model/');
		}*/


		/* >> Получаем объект выгрузки, который будем синхронизировать */
		$id = $this->getProperty('id');

		if( empty($id) )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_ns');
		}

		if( !$obj = $this->modx->getObject($this->classKey, $id) )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_nf');
		}

		$this->obj = $obj;
		/* << Получаем объект выгрузки, который будем синхронизировать */


		/* >> Получаем id msop2 опции - размер */
		if( !$msop2_option_size = $this->modx->getObject('msop2Option', array('key'=>'size')) )
		{
			$msop2_option_size = $this->modx->newObject( 'msop2Option', array(
				'name'			=> 'Размер',
				'key'			=> 'size',
				'description'	=> '',
				'rank'			=> '0',
				'active'		=> '1',
				'editable'		=> '1',
				'remains'		=> '1',
				'weight'		=> '1',
				'article'		=> '1',
			) );
			$msop2_option_size->save();
		}

		$this->msop2_option_size_id = $msop2_option_size->id;
		/* << Получаем id msop2 опции - размер */


		/* >> Получаем id связи - цвет */
		if( !$ms2_link_color = $this->modx->getObject('msLink', array('name'=>'giftscolor')) )
		{
			$ms2_link_color = $this->modx->newObject( 'msLink', array(
				'name'			=> 'giftscolor',
				'description'	=> '[gifts.ru] Цвета товаров',
				'type'			=> 'many_to_many',
			) );
			$ms2_link_color->save();
		}

		$this->color_link_id = $ms2_link_color->id;
		/* << Получаем id связи - цвет */


		return true;
	}


	/* >> Перезапуск скрипта в случае нехватки времени выполнения */
	private function restartScriptIfTimeIsRunningOut( $i=0, $max_i=0 )
	{
		$stock_seconds = 19; // запас времени
		$this->now_time = time(); // текущее время

		$time = $this->now_time - $this->restart_time;

		$done = ( $i >= $max_i ? true : false );
		$data['done'] = $done;
		$data['i'] = $i;
		$data['logTime'] = $this->logTime;
		$data['startTime'] = $this->start_time;

		if( ($this->max_exec_time - $time) < $stock_seconds && $this->restartScript )
		{
			$return['restart']	= true;
			$return['end']		= false;
		}
		else
		{
			$return['restart']	= false;
			$return['end']		= true;

			if( $done )
			{
				$return['restart']	= true;
			}
		}

		$return['data'] = $data;

		return $return;
	}
	/* << Перезапуск скрипта в случае нехватки времени выполнения */


	/* >> Логирование и замеры памяти */
	private function toLog( $string='' )
	{
		if( empty($this->logTime) || $this->logTime == '0' ) {
			$this->logTime = time();
		}

		$logPath = $this->assetsPath . 'logs/' . $this->obj->get('id') . '/'; // путь до папки с файлами логов

		// если папки нет - создадим
		if(	!file_exists( $logPath ) ) {
			mkdir( $logPath );
		}

		$memory = $this->convertBytes( memory_get_usage(true) );
		$datetime = date("j.m.Y H:i:s", time());

		$string = str_replace("--memory--", $memory, $string);

		$string = str_replace("--datetime--", $datetime, $string);

		file_put_contents( $logPath . $this->logTime . '.log', $string . '
', FILE_APPEND );
	}
	/* << Логирование и замеры памяти */


	public function process()
	{
		$error = false;

		/* >> Получаем введённые данные */
		$catTemplate = $this->getProperty('catTemplate'); // шаблон раздела
		if( empty($catTemplate) ) {
			$this->modx->error->addField('catTemplate', $this->modx->lexicon('msgiftsrusynch_item_err_cat_template'));
			$error = true;
		}

		$productTemplate = $this->getProperty('productTemplate'); // шаблон товара
		if( empty($productTemplate) ) {
			$productTemplate = $this->modx->getOption('ms2_template_product_default', null, '');
			if( empty($productTemplate) ) {
				$this->modx->error->addField('productTemplate', $this->modx->lexicon('msgiftsrusynch_item_err_product_template'));
				$error = true;
			}
		}

		$context = $this->getProperty('context'); // контекст
		if( empty($context) ) {
			$context = 'web';
		} else {
			if( $context == 'mgr' ) {
				$this->modx->error->addField('context', $this->modx->lexicon('msgiftsrusynch_item_err_context_mgr'));
				$error = true;
			}
		}

		$multiply = $this->getProperty('multiply'); // умножить цену на число
		$divide = $this->getProperty('divide'); // разделить цену на число

		if( $error ) {
			return $this->failure( $this->modx->lexicon('msgiftsrusynch_item_err') );
		}
		/* << Получаем введённые данные */


		// если только начали - запишем это в лог
		if( empty($this->logTime) )
		{
			$this->toLog( 'START --memory--, датавремя: --datetime--' );
		}


		$categories_content_default = $this->modx->getOption('ms2_category_content_default', null, '');
		$products_source_default = $this->modx->getOption('ms2_product_source_default', null, '2');


		$data = array(
			'catTemplate'		=> $catTemplate,
			'productTemplate'	=> $productTemplate,
			'context'			=> $context,
			'multiply'			=> $multiply,
			'divide'			=> $divide,
			'user_id'			=> $this->modx->user->id,
		);

		$data2 = array(
			'c'		=> array(
				'class'		=> array(
					'msCategory',
					'msCategoryMember',
					'msCategoryOption',
				),
				'alias'		=> array(
					'',
					'AlienProducts',
					'CategoryOptions',
				),
			),
			'o'		=> array(
				'class'		=> array(
					'msOption',
					'msCategoryOption',
					'msProductOption',
				),
				'alias'		=> array(
					'',
					'OptionCategories',
					'OptionProducts',
				),
			),
			'op'	=> array(
				'class'		=> array(
					'msop2Price',
					'msop2Option',
					'msop2Operation',
				),
				'alias'		=> array(
					'',
					'Option',
					'Operation',
				),
			),
			'p'		=> array(
				'class'		=> array(
					'msProduct',
					'msCategory',
					'msProductData',
					'msProductOption',
					'msCategoryMember',
				),
				'alias'		=> array(
					'',
					'Category',
					'Data',
					'Options',
					'Categories',
				),
			),
			'pl'	=> array(
				'class'		=> array(
					'msProductLink',
					'msLink',
					'msProduct',
					'msProduct',
				),
				'alias'		=> array(
					'',
					'Link',
					'Master',
					'Slave',
				),
			),
			'pf'	=> array(
				'class'		=> array(
					'msProductFile',
					'msProductFile',
					'msProductFile',
					'msProduct',
				),
				'alias'		=> array(
					'',
					'Children',
					'Parent',
					'Product',
				),
			),
		);


		/* >> Указываем поля для редактирования (после сравнения с которыми будет ясно, редактировать ли пункт) */
		$categories_fields_editable = array(
			$data2['c']['class'][0] .'.pagetitle',
			$data2['c']['class'][0] .'.template',
		);

		$options_fields_editable = array(
			$data2['o']['class'][0] .'.caption',
			$data2['o']['class'][0] .'.properties',
		);

		$products_fields_editable = array(
			$data2['p']['class'][0] .'.pagetitle',
			$data2['p']['class'][0] .'.content',
			$data2['p']['class'][0] .'.template',
			$data2['p']['class'][2] .'.price',
			$data2['p']['class'][2] .'.article',
			$data2['p']['class'][2] .'.weight',
		);
		/* << Указываем поля для редактирования (после сравнения с которыми будет ясно, редактировать ли пункт) */


		$xmlPath = $this->assetsPath . 'xml_files/' . $this->obj->get('id') . '/'; // путь до папки с файлами выгрузки

		if(	!file_exists( $xmlPath ) ||
			!file_exists( $xmlPath . $this->xmlFiles[0] ) ||
			!file_exists( $xmlPath . $this->xmlFiles[1] ) ||
			!file_exists( $xmlPath . $this->xmlFiles[2] ) ||
			!file_exists( $xmlPath . $this->xmlFiles[3] )
		) {
			return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_nff'));
		}
		else
		{
			/* >> treeWithoutProducts.xml - Разделы каталога */
			$xml_object = simplexml_load_file( $xmlPath . $this->xmlFiles[0] );
			$xml_array = $this->object2array( $xml_object->page );
			//return $this->failure( print_r( $xml_array, true ) );
			unset($xml_object);

			// Синхронизируем разделы
			if( !$this->synchCats($xml_array, array_merge(
				$data, $data2, array(
					'content_default'	=> $categories_content_default,
				)
			), $categories_fields_editable) ) {
				return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_synch'));
			}
			unset($xml_array);
			/* << treeWithoutProducts.xml - Разделы каталога */


			/* >> filters.xml - Доп поля */
			$xml_object = simplexml_load_file( $xmlPath . $this->xmlFiles[2] );
			$xml_array = $this->object2array( $xml_object->filtertypes );
			//return $this->failure( print_r( $xml_array['filtertype'], true ) );
			unset($xml_object);

			// Синхронизируем доп поля
			if( !$this->synchOptions($xml_array['filtertype'], array_merge(
				$data, $data2, array( )
			), $options_fields_editable) ) {
				return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_synch'));
			}
			unset($xml_array);
			/* << filters.xml - Доп поля */


			/* >> stock.xml - Кол-во остатка товаров */
			$xml_object = simplexml_load_file( $xmlPath . $this->xmlFiles[1] );
			$xml_array = $this->object2array( $xml_object );
			//return $this->failure( print_r( $xml_array, true ) );
			unset($xml_object);

			// Форумируем массив остатка товаров
			if( !$this->synchStock($xml_array) ) {
				return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_synch'));
			}
			unset($xml_array);
			/* << stock.xml - Кол-во остатка товаров */


			/* >> catalogue.xml - Товары + привязка к каталогам  */
			$xml_object = simplexml_load_file( $xmlPath . $this->xmlFiles[3] );
			$xml_array = $this->object2array( $xml_object );
			//file_put_contents( $xmlPath . 'auto'.time().'.xml', print_r($xml_array, true) );
			//return $this->failure( 'lala' );
			//return $this->failure( print_r( $xml_array['page'], true ) );
			unset($xml_object);

			// Синхронизируем товары с разделами
			if( !$this->synchProductsCats($xml_array['page']) ) {
				return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_synch'));
			}

			//return $this->failure( 'lala' );

			// для отладки
			//$xml_array['product'] = array_slice( $xml_array['product'], $this->iteration, 2 );

			// Синхронизируем товары
			$return = $this->synchProducts($xml_array['product'], array_merge(
				$data, $data2, array(
					'source_default'	=> $products_source_default,
				)
			), $products_fields_editable);

			unset($xml_array);
			/* << catalogue.xml - Товары + привязка к каталогам */

			//return $this->failure( 'ok' );
		}

		if( $return['end'] )
		{
			// >> Убираем с публикации товары отсутствующие в выгрузке
			/*return $this->failure(
				$this->synchProductsUnpublish(array_merge(
					$data, $data2
				))
			);*/
			$this->synchProductsUnpublish(array_merge(
				$data, $data2
			));
			// << Убираем с публикации товары отсутствующие в выгрузке

			$this->obj->set('active', '0');
			$this->obj->save();

			$this->toLog( 'END --memory--, датавремя: --datetime--' );
		}

		return $this->success('', $return['data']);
	}


	/* >> Работа с массивом разделов */
	public function synchCats( $array=array(), $data=array(), $fields_editable=array() )
	{
		if( !count($array) || !count($data) ) { return; }

		$add = false;
		$edit = array();

		if( isset($array['@attributes']) ) {
			if( isset($array['@attributes']['parent_page_id']) ) {
				$parent_page_id = $array['@attributes']['parent_page_id'];
			} else {
				$parent_page_id = '0';
			}
		} else {
			$parent_page_id = '0';
		}


		$q = $this->modx->newQuery( $data['c']['class'][0] );
		$q->select( array_merge( array("id"), $fields_editable ) );
		//$q->innerJoin('modTemplateVarTemplate', 'TVValues', "TVValues.contentid = '. $data['c']['class'][0] .'.id AND TVValues.tmplvarid = 8");
		$q->where( array(
				$data['c']['class'][0] .'.isfolder = 1'.
				' AND '.
				$data['c']['class'][0] .'.deleted = 0'.
				' AND '.
				$data['c']['class'][0] .'.class_key = "'. $data['c']['class'][0] .'"'.
				' AND '.
				$data['c']['class'][0] .'.context_key = "'. $data['context'] .'"'.
				' AND '.
				$data['c']['class'][0] .'.properties LIKE "%\"gifts.ru\":{\"page_id\":\"'. $array['page_id'] .'\"}%"'.
			'')
		);
		$q->limit(1);
		$s = $q->prepare();
		$s->execute();
		$row = $s->fetch(PDO::FETCH_ASSOC);
		unset($q);
		unset($s);

		if( is_array($row) && isset($row['id']) )
		{
			$add = false;

			if( $row['pagetitle'] != $array['name'] && in_array( $data['c']['class'][0] .'.pagetitle', $fields_editable ) ) {
				$edit[] = 'pagetitle';
			}

			if( $row['template'] != $data['catTemplate'] && in_array( $data['c']['class'][0] .'.template', $fields_editable ) ) {
				$edit[] = 'template';
			}
		}
		else {
			$add = true;
		}

		//if( $this->all_categories_i > 6 ) { return array( 'return' => $this->all_categories_i ); }

		/* >> Добавление */
		if( $add )
		{
			// >> получаем id родителя
			if( $parent_page_id > 0 ) {
				$q = $this->modx->newQuery( $data['c']['class'][0] );
				$q->select( array(
						"id"
					)
				);
				$q->where( array(
						$data['c']['class'][0] .'.isfolder = 1'.
						' AND '.
						$data['c']['class'][0] .'.deleted = 0'.
						' AND '.
						$data['c']['class'][0] .'.class_key = "'. $data['c']['class'][0] .'"'.
						' AND '.
						$data['c']['class'][0] .'.context_key = "'. $data['context'] .'"'.
						' AND '.
						$data['c']['class'][0] .'.properties LIKE "%\"page_id\":\"'. $parent_page_id .'\"%"'.
					'')
				);
				$q->limit(1);
				$s = $q->prepare();
				$s->execute();
				$parent_row = $s->fetch(PDO::FETCH_ASSOC);
				unset($q);
				unset($s);

				if( is_array($parent_row) && isset($parent_row['id']) ) {
					$parent_id = $parent_row['id'];
				} else {
					$parent_id = '0';
				}
			} else {
				$parent_id = '0';
			}
			// << получаем id родителя

			$fields = array(
				'type'					=> 'document',
				'contentType'			=> 'text/html',
				'pagetitle'				=> $array['name'],
				'alias'					=> $array['uri'],
				'published'				=> '1',
				'parent'				=> $parent_id,
				'isfolder'				=> '1',
				'introtext'				=> '',
				'content'				=> $data['content_default'],
				'richtext'				=> '0',
				'template'				=> $data['catTemplate'],
				'menuindex'				=> '0', // каким по счёту отображать в админке
				'searchable'			=> '1',
				'cacheable'				=> '1',
				'createdby'				=> $data['user_id'],
				'createdon'				=> time(),
				'editedby'				=> '0',
				'editedon'				=> '0',
				'publishedon'			=> time(),
				'publishedby'			=> $data['user_id'],
				'class_key'				=> $data['c']['class'][0],
				'context_key'			=> $data['context'],
				'content_type'			=> '1',
				'show_in_tree'			=> '1',
				'properties'			=> $this->modx->toJSON( array( 'gifts.ru' => array( 'page_id'=>$array['page_id'] ) ) ),
			);
			//return array( 'return' => print_r($fields, true) );

			$res = $this->modx->newObject( $data['c']['class'][0] , $fields );
			$res->save();

			$row['id'] = $res->id;

			$this->new_categories_i++;
		}
		/* << Добавление */


		/* >> Редактирование */
		if( count($edit) > 0 )
		{
			$res = $this->modx->getObject( $data['c']['class'][0] , $row['id'] );

			if( in_array('pagetitle', $edit) ) {
				$res->set('pagetitle', $array['name']);
			}

			if( in_array('template', $edit) ) {
				$res->set('template', $data['catTemplate']);
			}

			$res->set('editedby', $data['user_id']);
			$res->set('editedon', time());

			$res->save();

			$this->edit_categories_i++;
		}
		/* << Редактирование */


		$this->all_categories_i++;

		$this->array_categories[ $array['page_id'] ] = array(
			'id' => $row['id'],
			'pagetitle' => $array['name'],
		);


		if( isset($array['page']) && is_array($array['page']) )
		{
			foreach( $array['page'] as $v )
			{
				$this->synchCats($v, $data, $fields_editable);
			}
		}

		return true;
		//return array( 'return' => $this->all_categories_i );
	}
	/* << Работа с массивом разделов */


	/* >> Работа с массивом доп полей */
	public function synchOptions( $array=array(), $data=array(), $fields_editable=array() )
	{
		if( !count($array) || !count($data) ) { return; }

		$add = false;
		$edit = array();


		foreach( $array as $option )
		{
			// >> Разбираем возможные значения в массив
			$option_properties = array();
			$option_properties_with_ids = array();

			foreach( $option['filters']['filter'] as $k => $v )
			{
				$option_properties[] = $v['filtername'];
				$option_properties_with_ids[ $v['filterid'] ] = $v['filtername'];
			}
			// << Разбираем возможные значения в массив

			$q = $this->modx->newQuery( $data['o']['class'][0] );
			$q->select( array_merge( array("id","key"), $fields_editable ) );
			$q->where( array(
					$data['o']['class'][0] .'.key = "gifts'. $option['filtertypeid'] .'"'.
				'')
			);
			$q->limit(1);
			$s = $q->prepare();
			$s->execute();
			$row = $s->fetch(PDO::FETCH_ASSOC);
			unset($q);
			unset($s);

			if( is_array($row) )
			{
				$add = false;

				if( $row['caption'] != $option['filtertypename'] && in_array( $data['o']['class'][0] .'.caption', $fields_editable ) ) {
					$edit[] = 'caption';
				}

				// >> Варианты возможных значений
				if( in_array( $data['o']['class'][0] .'.properties', $fields_editable ) )
				{
					$properties = array();
					$properties_notfound = array();

					$properties = $this->modx->fromJSON($row['properties']);

					if( isset($properties['values']) )
					{
						foreach( $option_properties as $k => $v )
						{
							if( !in_array( $v, $properties ) )
							{
								$properties_notfound[] = $v;

								$this->new_options_values_i++;
							}
						}
					}

					if( count($properties_notfound) > 0 ) {
						$edit[] = 'properties';
					}
				}
				// << Варианты возможных значений
			}
			else {
				$add = true;

				$this->new_options_values_i += count($option_properties);
			}

			//if( $this->all_options_i > 0 ) { return array( 'return' => $this->all_options_i ); }

			/* >> Добавление */
			if( $add )
			{
				$fields = array(
					'key'					=> "gifts". $option['filtertypeid'],
					'caption'				=> $option['filtertypename'],
					'category'				=> '0',
					'type'					=> 'combo-multiple',
					'properties'			=> $this->modx->toJSON( array( 'values' => $option_properties ) ),
				);
				//return array( 'return' => print_r($fields, true) );

				$opt = $this->modx->newObject( $data['o']['class'][0] , $fields );
				$opt->save();

				$row['id'] = $opt->id;
				$row['key'] = $opt->key;

				$this->new_options_i++;
			}
			/* << Добавление */


			/* >> Редактирование */
			if( count($edit) > 0 )
			{
				$opt = $this->modx->getObject( $data['o']['class'][0] , $row['id'] );

				if( in_array('caption', $edit) ) {
					$opt->set('caption', $option['filtertypename']);
				}

				if( in_array('properties', $edit) )
				{
					$props = $this->modx->fromJSON( $opt->properties );

					$opt->set('properties', $this->modx->toJSON( array( 'values' => array_merge( $props['values'], array_diff( $option_properties, $props['values'] ) ) ) ) );
				}

				$opt->save();

				$this->edit_options_i++;
			}
			/* << Редактирование */


			$this->all_options_i++;

			$this->array_options[ $option['filtertypeid'] ] = array(
				'id' => $row['id'],
				'key' => $row['key'],
				'caption' => $option['filtertypename'],
				'properties' => $option_properties_with_ids,
			);
		}

		return true;
		//return array( 'return' => $this->all_categories_i );
	}
	/* << Работа с массивом доп полей */


	/* >> Работа с остатком товаров */
	public function synchStock( $array=array() )
	{
		if( !count($array) ) { return; }

		if( isset($array['stock']) && is_array($array['stock']) )
		{
			foreach( $array['stock'] as $v )
			{
				$this->array_stock_products[ $v['product_id'] ] = array(
					'amount'		=> $v['amount'],
					'free'			=> $v['free'],
					'inwayamount'	=> $v['inwayamount'],
					'inwayfree'		=> $v['inwayfree'],
				);
			}
		}

		return true;
	}
	/* << Работа с остатком товаров */


	/* >> Работа с массивом связей разделов и товаров */
	public function synchProductsCats( $array=array() )
	{
		if( !count($array) ) { return; }

		if( isset($array['product']) && is_array($array['product']) )
		{
			foreach( $array['product'] as $v )
			{
				if( isset($v['product']) && isset($v['page']) )
				{
					$this->array_products_categories[ $v['product'] ][] = array(
						'id'		=> $this->array_categories[ $v['page'] ]['id'],
						'page_id'	=> $v['page'],
					);
				}
			}
		}
		elseif( isset($array['page']) && is_array($array['page']) )
		{
			foreach( $array['page'] as $v )
			{
				$this->synchProductsCats( $v );
			}
		}

		return true;
	}
	/* << Работа с массивом связей разделов и товаров */


	/* >> Работа с массивом товаров */
	public function synchProducts( $array=array(), $data=array(), $fields_editable=array() )
	{
		if( !count($array) || !count($data) ) { return; }
		//return array('return'=>$array[0]);

		$max_iterations = count($array);
		$iteration_key = $this->iteration;

		foreach( array_slice( $array, $this->iteration, count($array) ) as $v )
		{
			/* >> Выполняем действия над товаром (добавление, редактирование) */
			//return array('return'=>$this->synchProductsAction( $v, $data, $fields_editable ));
			$row = $this->synchProductsAction( $v, $data, $fields_editable );
			/* << Выполняем действия над товаром (добавление, редактирование) */


			/* >> Добавляем товар в дополнительные разделы (категории) */
			if( isset($this->array_products_categories[ $v['product_id'] ]) && count($this->array_products_categories[ $v['product_id'] ]) > 1 )
			{
				$product_cats_array=array();

				$q = $this->modx->newQuery( $data['p']['class'][4] );
				$q->select( array_merge(
					array(
						$data['p']['class'][4] .".category_id",
					)
				) );
				$q->where( array(
						$data['p']['class'][4] .'.product_id = '. $row['id'] .''.
					'')
				);
				$s = $q->prepare(); //return array('return'=>$q->toSQL());
				$s->execute();
				$pc_rows = $s->fetchAll(PDO::FETCH_ASSOC);
				unset($q);
				unset($s);
				//return array('return'=>print_r($pc_rows,true));

				foreach( $pc_rows as $pc_row )
				{
					if( !in_array( $pc_row['category_id'], $product_cats_array ) )
					{
						$product_cats_array[] = $pc_row['category_id'];
					}
				}

				foreach( array_slice( $this->array_products_categories[ $v['product_id'] ] , 1, count( $this->array_products_categories[ $v['product_id'] ] ) ) as $product_cat )
				{
					if( !in_array( $product_cat['id'], $product_cats_array ) )
					{
						$pc_obj = $this->modx->newObject( $data['p']['class'][4] );

						$pc_obj->set('product_id',	$row['id']);
						$pc_obj->set('category_id',	$product_cat['id']);

						$pc_obj->save();

						unset($pc_obj);
					}
					else {
						unset( $product_cats_array[ array_search( $product_cat['id'], $product_cats_array ) ] );
					}
				}

				if( count($product_cats_array) > 0 )
				{
					foreach( $product_cats_array as $product_cat )
					{
						$q = $this->modx->newQuery( $data['p']['class'][4] );
						$q->command('delete');
						$q->where( array(
							'product_id'	=> $row['id'],
							'category_id'	=> $product_cat['id'],
						) );
						$q->prepare();
						//return array('return'=>print_r($q->toSQL(),true));
						$q->stmt->execute();
						unset($q);
					}
				}
			}
			/* << Добавляем товар в дополнительные разделы (категории) */


			/* >> Связываем одинаковые товары разного цвета (тип связи: многие ко многим) */
			//if($row['id']=='495') return array('return'=>$this->synchProductsLinks( $v, $row, $data ));
			$this->synchProductsLinks( $v, $row, $data );
			/* << Связываем одинаковые товары разного цвета (тип связи: многие ко многим) */


			/* >> Дополнительные поля товара */
			$product_params = array(
				'id'		=> $row['id'],
				'parent'	=> $row['parent'],
			);

			// >> Поле "Размеры"
			$sizes = array();
			if( isset($v['product']) )
			{
				$sizes_products = array();

				if( !isset($v['product'][0]) ) {
					$sizes_products[0] = $v['product'];
				} else {
					$sizes_products = $v['product'];
				}

				foreach( $sizes_products as $sizes_product_data )
				{
					if( isset($sizes_product_data['size_code']) )
					{
						$sizes[] = $sizes_product_data['size_code'];
					}
				}
				//return array('return'=>$sizes);

				$res_obj = $this->modx->getObject( $data['p']['class'][0] , $row['id'] );
				$res_obj->set('size', $this->modx->toJSON( $sizes ));
				$res_obj->save();
				unset($res_obj);
			}
			// << Поле "Размеры"

			// >> Поле "Статус"
			if( isset($v['status']) )
			{
				if( !empty($v['status']) )
				{
					$opt_params = array(
						'key'		=> 'status',
						'caption'	=> 'Статус',
						'type'		=> 'combobox',
					);

					//return array( 'return' => $this->synchProductsOptions( $v['print'], $data, $opt_params, $product_params ) );
					$this->synchProductsOptions( array('name'=>$v['status']), $data, $opt_params, $product_params );
				}
			}
			// << Поле "Статус"

			// >> Поле "Количество в упаковке"
			if( isset($v['pack']['amount']) )
			{
				$packamount = (int)$v['pack']['amount'];

				if( !empty($packamount) )
				{
					$opt_params = array(
						'key'		=> 'packamount',
						'caption'	=> 'Количество в упаковке',
						'type'		=> 'numberfield',
					);

					$this->synchProductsOptions( array('name'=>$packamount), $data, $opt_params, $product_params );
				}
			}
			// << Поле "Количество в упаковке"

			// >> Поле "Вес в упаковке (г)"
			if( isset($v['pack']['weight']) )
			{
				$packweight = (float)$v['pack']['weight'];

				if( !empty($packweight) )
				{
					$opt_params = array(
						'key'		=> 'packweight',
						'caption'	=> 'Вес в упаковке (г)',
						'type'		=> 'textfield',
					);

					$this->synchProductsOptions( array('name'=>$packweight), $data, $opt_params, $product_params );
				}
			}
			// << Поле "Вес в упаковке (г)"

			// >> Поле "Объём упаковки (см3)"
			if( isset($v['pack']['volume']) )
			{
				$packvolume = (float)$v['pack']['volume'];

				if( !empty($packvolume) )
				{
					$opt_params = array(
						'key'		=> 'packvolume',
						'caption'	=> 'Объём упаковки (см3)',
						'type'		=> 'textfield',
					);

					$this->synchProductsOptions( array('name'=>$packvolume), $data, $opt_params, $product_params );
				}
			}
			// << Поле "Объём упаковки (см3)"

			// >> Поле "Размер упаковки - ШxВxГ (см)"
			if( isset($v['pack']['sizex']) && isset($v['pack']['sizey']) && isset($v['pack']['sizez']) )
			{
				$sizex = (float)$v['pack']['sizex'];
				$sizey = (float)$v['pack']['sizey'];
				$sizez = (float)$v['pack']['sizez'];

				if( !empty($sizex) && !empty($sizey) && !empty($sizez) )
				{
					$opt_params = array(
						'key'		=> 'packsize',
						'caption'	=> 'Размер упаковки - ШxВxГ (см)',
						'type'		=> 'textfield',
					);

					$this->synchProductsOptions( array('name'=>$sizex.'x'.$sizey.'x'.$sizez), $data, $opt_params, $product_params );
				}
			}
			// << Поле "Размер упаковки - ШxВxГ (см)"

			// >> Поле "Виды нанесения"
			if( isset($v['print']) )
			{
				if( !empty($v['print']) )
				{
					$opt_params = array(
						'key'		=> 'print',
						'caption'	=> 'Виды нанесения',
						'type'		=> 'combo-multiple',
					);

					$this->synchProductsOptions( $v['print'], $data, $opt_params, $product_params );
				}
			}
			// << Поле "Виды нанесения"

			// >> Поле "Кол-во"
			if( isset($this->array_stock_products[ $v['product_id'] ]) )
			{
				if( !empty($this->array_stock_products[ $v['product_id'] ]) )
				{
					$opt_params = array(
						'key'		=> 'count',
						'caption'	=> 'На складе',
						'type'		=> 'numberfield',
					);

					$this->synchProductsOptions( array('name'=>$this->array_stock_products[ $v['product_id'] ]['free']), $data, $opt_params, $product_params );
				}
			}
			// << Поле "Кол-во"

			// >> Поля из filters.xml
			if( isset($v['filters']['filter']) )
			{
				if( !empty($v['filters']['filter']) )
				{
					if( !isset($v['filters']['filter'][0]) ) {
						$filters_filter[0] = $v['filters']['filter'];
					} else {
						$filters_filter = $v['filters']['filter'];
					}

					$filtertypeids = array();

					foreach( $filters_filter as $filter_data )
					{

						$opt_params = array(
							'key'			=> $filter_data['filtertypeid'],
							'caption'		=> $this->array_options[ $filter_data['filtertypeid'] ]['caption'],
							'type'			=> 'combo-multiple',
							'optionallyadd'	=> in_array( $filter_data['filtertypeid'], $filtertypeids) ? true : false,
						);

						$filtertypeids[] = $filter_data['filtertypeid'];

						$this->synchProductsOptions( array('name'=>$this->array_options[ $filter_data['filtertypeid'] ]['properties'][ $filter_data['filterid'] ]), $data, $opt_params, $product_params );
					}
				}
			}
			// << Поля из filters.xml
			/* << Дополнительные поля товара */


			/* >> Опции товара для msop2 - размер */
			$this->synchProductsMsop2Options( $v, $row, $sizes, $data );
			/* << Опции товара для msop2 - размер */


			/* >> Загружаем файлы товара */
			if( isset($v['product_attachment']) && count($v['product_attachment']) )
			{
				$return = $this->synchProductsFiles( $v['product_attachment'], $row, array_merge( $data, array(
					'iteration_key'		=> $iteration_key,
					'max_iterations'	=> $max_iterations,
				) ) );

				if( $return['restart'] )
				{
					return $return;
				}
			}
			/* << Загружаем файлы товара */


			/* >> сортируем файлы товара */
			$this->synchProductsFilesSort( $v['small_image']['@attributes']['src'], $row, $data );
			/* >> сортируем файлы товара */


			$iteration_key++;

			$this->toLog( "--memory-- || --datetime-- || итерация: " . $iteration_key . " || res_id: " . $row['id'] . " || product_id: " . $v['product_id'] );

			$return = $this->restartScriptIfTimeIsRunningOut( $iteration_key, $max_iterations );

			if( $return['restart'] )
			{
				return $return;
			}
		}

		return true;
	}
	/* << Работа с массивом товаров */


	/* >> Добавление или редактирование товаров */
	public function synchProductsAction( $array=array(), $data=array(), $fields_editable=array() )
	{
		if( !count($array) || !count($data) ) { return; }
		//return array('return'=>$array);

		$add = false;
		$edit = array();


		$v = $array;

		// >> работаем с ценой
		$price = $this->convertPrice($v['price']['price'], $data);
		//return array('return'=>$price);
		// << работаем с ценой

		$q = $this->modx->newQuery( $data['p']['class'][0] );
		$q->select( array_merge(
			array(
				$data['p']['class'][0] .".id",
				$data['p']['class'][0] .".parent",
			),
			$fields_editable
		) );
		$q->leftJoin( $data['p']['class'][2], $data['p']['class'][2], $data['p']['class'][2] .'.id = '. $data['p']['class'][0] .'.id');
		$q->where( array(
				$data['p']['class'][0] .'.isfolder = 0'.
				' AND '.
				$data['p']['class'][0] .'.deleted = 0'.
				' AND '.
				$data['p']['class'][0] .'.class_key = "'. $data['p']['class'][0] .'"'.
				' AND '.
				$data['p']['class'][0] .'.context_key = "'. $data['context'] .'"'.
				' AND '.
				$data['p']['class'][0] .'.properties LIKE "%\"product_id\":\"'. $v['product_id'] .'\"%"'.
			'')
		);
		$q->limit(1);
		$s = $q->prepare(); //return array('return'=>$q->toSQL());
		$s->execute();
		$row = $s->fetch(PDO::FETCH_ASSOC);
		unset($q);
		unset($s);
		//return array('return'=>print_r($row,true));

		if( is_array($row) && isset($row['id']) )
		{
			$add = false;

			if( $row['pagetitle'] != $v['name'] && in_array( $data['p']['class'][0] .'.pagetitle', $fields_editable ) ) {
				$edit[] = 'pagetitle';
			}

			if( $row['content'] != $v['content'] && in_array( $data['p']['class'][0] .'.content', $fields_editable ) ) {
				$edit[] = 'content';
			}

			if( $row['template'] != $data['productTemplate'] && in_array( $data['p']['class'][0] .'.template', $fields_editable ) ) {
				$edit[] = 'template';
			}

			if( round($row['price']) != $price && in_array( $data['p']['class'][2] .'.price', $fields_editable ) ) {
				$edit[] = 'price';
			}

			if( isset($v['code']) && ( $row['article'] != $v['code'] && in_array( $data['p']['class'][2] .'.article', $fields_editable ) ) ) {
				$edit[] = 'article';
			}

			if( isset($v['weight']) && ( $row['weight'] != $v['weight'] && in_array( $data['p']['class'][2] .'.weight', $fields_editable ) ) ) {
				$edit[] = 'weight';
			}
		}
		else {
			$add = true;
		}


		/* >> Добавление */
		if( $add )
		{
			// >> получаем id главного (первого) родителя
			if( isset($this->array_products_categories[ $v['product_id'] ][0]['id']) )
			{
				$parent_id = $this->array_products_categories[ $v['product_id'] ][0]['id'];
			}
			else {
				$parent_id = '0';
			}
			// << получаем id родителя

			if( $parent_id > 0 )
			{
				$properties = array();

				$properties['gifts.ru']['product_id'] = $v['product_id'];

				if( isset($v['group']) && !empty($v['group']) )
				{
					$properties['gifts.ru']['group'] = $v['group'];
				}

				$fields = array(
					'type'					=> 'document',
					'contentType'			=> 'text/html',
					'pagetitle'				=> $v['name'],
					'alias'					=> $v['product_id'],
					'published'				=> '1',
					'parent'				=> $parent_id,
					'isfolder'				=> '0',
					'introtext'				=> '',
					'content'				=> $v['content'],
					'richtext'				=> '0',
					'template'				=> $data['productTemplate'],
					'menuindex'				=> '0', // каким по счёту отображать в админке
					'searchable'			=> '1',
					'cacheable'				=> '1',
					'createdby'				=> $data['user_id'],
					'createdon'				=> time(),
					'editedby'				=> $data['user_id'],
					'editedon'				=> time(),
					'publishedby'			=> $data['user_id'],
					'publishedon'			=> time(),
					'class_key'				=> $data['p']['class'][0],
					'context_key'			=> $data['context'],
					'content_type'			=> '1',
					'show_in_tree'			=> '0',
					'properties'			=> $this->modx->toJSON( $properties ),

					'source'				=> $data['source_default'],
					'price'					=> $price,
					'article'				=> $v['code'],
					'weight'				=> $v['weight'],
					'size'					=> $this->modx->toJSON( array( $v['product_size'] ) ),
				);
				//return array( 'return' => print_r($fields, true) );

				$res = $this->modx->newObject( $data['p']['class'][0] , $fields );
				$res->save();

				$row['id'] = $res->id;
				$row['parent'] = $parent_id;

				$this->new_products_i++;
			}
		}
		/* << Добавление */


		if( !$add )
		{
			$res = $this->modx->getObject( $data['p']['class'][0] , $row['id'] );

			$res->set('source', $data['source_default']);

			$res->set('editedby', $data['user_id']);
			$res->set('editedon', time());

			$res->set('published', '1');
			$res->set('publishedon', time());
			$res->set('publishedby', $data['user_id']);
		}


		/* >> Редактирование */
		if( count($edit) > 0 )
		{
			if( in_array('pagetitle', $edit) ) {
				$res->set('pagetitle', $v['name']);
			}

			if( in_array('content', $edit) ) {
				$res->set('content', $v['content']);
			}

			if( in_array('template', $edit) ) {
				$res->set('template', $data['productTemplate']);
			}

			if( in_array('price', $edit) ) {
				$res->set('price', $price);
			}

			if( in_array('article', $edit) ) {
				$res->set('article', $v['code']);
			}

			if( in_array('weight', $edit) ) {
				$res->set('weight', $v['weight']);
			}

			$this->edit_products_i++;
		}
		/* << Редактирование */


		if( !$add )
		{
			$res->save();
		}


		$this->all_products_i++;

		return $row;
	}
	/* << Добавление или редактирование товаров */


	/* >> Работа с опциями товара для msop2 (добавление или редактирование) */
	public function synchProductsMsop2Options( $v=array(), $row=array(), $sizes=array(), $data=array() )
	{
		if( !count($v) || !count($row) || !count($data) ) { return; }
		//return array('return'=>$array);

		$rows_saved = array();

		if( !empty($this->msop2_option_size_id) )
		{
			$q = $this->modx->newQuery( $data['op']['class'][0] );
			$q->select( array_merge(
				array(
					$data['op']['class'][0] .".id",
					$data['op']['class'][0] .".value",
					$data['op']['class'][0] .".price",
					$data['op']['class'][0] .".article",
					$data['op']['class'][0] .".count",
					$data['op']['class'][0] .".weight",
					$data['op']['class'][0] .".properties",
				)
			) );
			$q->where( array(
					$data['op']['class'][0] .'.product_id = '. $row['id'] .''.
					' AND '.
					$data['op']['class'][0] .'.option = '. $this->msop2_option_size_id .''.
				'')
			);
			$s = $q->prepare(); //return array('return'=>$q->toSQL());
			$s->execute();
			$op_rows = $s->fetchAll(PDO::FETCH_ASSOC);
			unset($q);
			unset($s);
			//return array('return'=>print_r($op_rows,true));

			if( is_array($op_rows) && count($op_rows) > 0 )
			{
				foreach( $op_rows as $op_row )
				{
					if( !in_array( $op_row['value'], $sizes) )
					{
						$q = $this->modx->newQuery( $data['op']['class'][0] );
						$q->command('delete');
						$q->where( array(
							'id'	=> $op_row['id'],
						) );
						$q->prepare();
						//return array('return'=>print_r($q->toSQL(),true));
						$q->stmt->execute();
						unset($q);
					}
					else {
						unset( $sizes[ array_search( $op_row['value'], $sizes ) ] );

						// сохраняем данные каждого значения во временный массив
						$rows_saved[ $op_row['value'] ] = array(
							'id'			=> $op_row['id'],
							'price'			=> $op_row['price'],
							'article'		=> $op_row['article'],
							'count'			=> $op_row['count'],
							'weight'		=> $op_row['weight'],
							'properties'	=> $op_row['properties'],
						);
					}
				}
			}

			if( isset($v['product']) )
			{
				$sizes_products = array();

				if( !isset($v['product'][0]) ) {
					$sizes_products[0] = $v['product'];
				} else {
					$sizes_products = $v['product'];
				}

				foreach( $sizes_products as $spd )
				{
					$edit = array();

					// цена
					$spd_price = '0';
					if( isset($spd['price']['price']) )
					{
						$spd_price = $this->convertPrice($spd['price']['price'], $data);
					}

					// артикул
					$spd_article = ( isset($spd['code']) ? $spd['code'] : '' );

					// кол-во
					$spd_count = ( isset($this->array_stock_products[ $spd['product_id'] ]['free']) ? $this->array_stock_products[ $spd['product_id'] ]['free'] : '0' );

					// вес
					$spd_weight = ( isset($spd['weight']) ? $spd['weight'] : '0' );

					// доп свойства
					$spd_properties = array(
						'gifts.ru'		=> array(
							'product_id'	=> $spd['product_id'],
							'main_product'	=> $spd['main_product'],
					));

					// >> проверяем на нужду редактирования
					if( isset($rows_saved[ $spd['size_code'] ]) )
					{
						if( $rows_saved[ $spd['size_code'] ]['price'] != $spd_price )
						{
							$edit[] = 'price';
						}

						if( $rows_saved[ $spd['size_code'] ]['article'] != $spd_article )
						{
							$edit[] = 'article';
						}

						if( $rows_saved[ $spd['size_code'] ]['count'] != $spd_count )
						{
							$edit[] = 'count';
						}

						if( $rows_saved[ $spd['size_code'] ]['weight'] != $spd_weight )
						{
							$edit[] = 'weight';
						}
					}
					// << проверяем на нужду редактирования

					if( isset($spd['size_code']) && in_array($spd['size_code'], $sizes) && !count($edit) )
					{
						$op_obj = $this->modx->newObject( $data['op']['class'][0], array(
							'product_id'	=> $row['id'],
							'option'		=> $this->msop2_option_size_id,
							'value'			=> $spd['size_code'],
							'price'			=> $spd_price,
							'article'		=> $spd_article,
							'count'			=> $spd_count,
							'weight'		=> $spd_weight,
							'operation'		=> '1',
							'active'		=> '1',
							'properties'	=> $this->modx->toJSON($spd_properties),
						) );
						$op_obj->save();
						unset($op_obj);
					}
					elseif( count($edit) > 0 )
					{
						$op_obj = $this->modx->getObject( $data['op']['class'][0], $rows_saved[ $spd['size_code'] ]['id'] );

						if( in_array('price', $edit) ) {
							$op_obj->set('price', $spd_price);
						}

						if( in_array('article', $edit) ) {
							$op_obj->set('article', $spd_article);
						}

						if( in_array('count', $edit) ) {
							$op_obj->set('count', $spd_count);
						}

						if( in_array('weight', $edit) ) {
							$op_obj->set('weight', $spd_weight);
						}

						$op_obj->set('properties', $this->modx->toJSON($spd_properties) );

						$op_obj->save();
					}
				}
			}
		}
	}
	/* << Работа с опциями товара для msop2 (добавление или редактирование) */


	/* >> Работа с массивом связей разделов и товаров */
	public function synchProductsOptions( $array=array(), $data=array(), $option=array(), $product=array() )
	{
		if( !count($array) || !count($data) || !count($option) || !count($product) ) { return; }

		$add = false;
		$edit = array();

		if( !isset($array[0]) ) {
			$arr[0] = $array;
		} else {
			$arr = $array;
		}

		// >> Разбираем возможные значения в массив
		$option_properties = array();
		$option_properties_with_ids = array();

		foreach( $arr as $v )
		{
			$option_properties[] = $v['name'] . ( ( isset($v['description']) && $v['description'] != '' ) ? ' - '. $v['description'] : '' );
			$option_properties_with_ids[ $v['name'] ] = $v['name'] . ( ( isset($v['description']) && $v['description'] != '' ) ? ' - '. $v['description'] : '' );
		}
		// << Разбираем возможные значения в массив


		$q = $this->modx->newQuery( $data['o']['class'][0] );
		$q->select( array_merge(
			array(
				$data['o']['class'][0] .".id",
				$data['o']['class'][0] .".key",
				$data['o']['class'][0] .".type",
				$data['o']['class'][0] .".properties",
			)
		) );
		$q->where( array(
				$data['o']['class'][0] .'.key = "gifts'. $option['key'] .'"'.
			'')
		);
		$q->limit(1);
		$s = $q->prepare(); //return array('return'=>$q->toSQL());
		$s->execute();
		$row = $s->fetch(PDO::FETCH_ASSOC);
		unset($q);
		unset($s);
		//return array('return'=>print_r($row,true));


		if( is_array($row) && isset($row['key']) )
		{
			$add = false;

			if( in_array( $row['type'], array('combo-multiple', 'combobox') ) )
			{
				$properties = $this->modx->fromJSON($row['properties']);

				if( isset($properties['values']) )
				{
					for( $i=0; $i<count($option_properties); $i++ )
					{
						if( !in_array( $option_properties[$i], $properties['values'] ) ) {
							$edit[] = 'properties';
						}
					}
				}
			}
		}
		else {
			$add = true;

			$this->new_options_values_i += count($option_properties);
		}


		/* >> Добавление */
		if( $add )
		{
			$fields = array(
				'key'					=> "gifts". $option['key'],
				'caption'				=> $option['caption'],
				'category'				=> '0',
				'type'					=> $option['type'],
				'properties'			=> ( in_array( $option['type'], array('combo-multiple', 'combobox') ) ? $this->modx->toJSON( array( 'values' => $option_properties ) ) : '' ),
			);
			//return array( 'return' => print_r($fields, true) );

			$opt = $this->modx->newObject( $data['o']['class'][0] , $fields );
			$opt->save();

			$row['id'] = $opt->id;
			$row['key'] = $opt->key;

			$this->new_options_i++;
		}
		/* << Добавление */


		/* >> Редактирование */
		if( count($edit) > 0 )
		{
			$opt = $this->modx->getObject( $data['o']['class'][0] , $row['id'] );

			if( in_array('properties', $edit) )
			{
				$opt->set('properties', $this->modx->toJSON( array( 'values' => array_merge( $properties['values'], array_diff( $option_properties, $properties['values'] ) ) ) ) );
			}

			$opt->save();

			$this->edit_options_i++;
		}
		/* << Редактирование */


		/* >> Привязываем опцию к категории */
		if( count($product) > 0 )
		{
			$q = $this->modx->newQuery( $data['o']['class'][1] );
			$q->select( array_merge(
				array(
					$data['o']['class'][1] .".option_id",
				)
			) );
			$q->where( array(
					$data['o']['class'][1] .'.option_id = '. $row['id'] .''.
					' AND '.
					$data['o']['class'][1] .'.category_id = '. $product['parent'] .''.
				'')
			);
			$q->limit(1);
			$s = $q->prepare(); //return array('return'=>$q->toSQL());
			$s->execute();
			$co_row = $s->fetch(PDO::FETCH_ASSOC);
			unset($q);
			unset($s);
			//return array('return'=>print_r($co_row,true));

			if( is_array($co_row) && isset($co_row['option_id']) )
			{
				$co_add = false;
			}
			else {
				$co_add = true;
			}

			// >> Добавление
			if( $co_add )
			{
				$co_fields = array(
					'rank'					=> '0',
					'active'				=> '1',
					'required'				=> '0',
					'value'					=> '',
				);
				//return array( 'return' => print_r($co_fields, true) );

				$co_opt = $this->modx->newObject( $data['o']['class'][1] , $co_fields );
				//return array( 'return' => print_r($co_opt, true) );

				$co_opt->set('option_id', $row['id']);
				$co_opt->set('category_id', $product['parent']);

				$co_opt->save();
				//return array( 'return' => print_r($co_opt->toArray(), true) );
			}
			// << Добавление
		}
		/* << Привязываем опцию к категории */


		/* >> Привязываем значения опции к товару */
		if( count($product) > 0 )
		{
			$q = $this->modx->newQuery( $data['o']['class'][2] );
			$q->select( array_merge(
				array(
					$data['o']['class'][2] .".key",
					$data['o']['class'][2] .".value",
				)
			) );
			$q->where( array(
					$data['o']['class'][2] .'.key = "gifts'. $option['key'] .'"'.
					' AND '.
					$data['o']['class'][2] .'.product_id = '. $product['id'] .''.
				'')
			);
			$s = $q->prepare(); //return array('return'=>$q->toSQL());
			$s->execute();
			$po_rows = $s->fetchAll(PDO::FETCH_ASSOC);
			unset($q);
			unset($s);
			//return array('return'=>print_r($po_rows,true));

			if( is_array($po_rows) && count($po_rows) )
			{
				for( $a=0; $a<count($po_rows); $a++ )
				{
					// если в базе нет одного из возможных значений, то удаляем из базы то, чего нет.. при условии, что параметр "optionallyadd" (дополнительно добавить) = 0
					if( !in_array( $po_rows[$a]['value'], $option_properties ) && empty($option['optionallyadd']) )
					{
						$q = $this->modx->newQuery( $data['o']['class'][2] );
						$q->command('delete');
						$q->where( array(
							'product_id'	=> $product['id'],
							'key'			=> $po_rows[$a]['key'],
							'value'			=> $po_rows[$a]['value'],
						) );
						$q->prepare();
						//return array('return'=>print_r($q->toSQL(),true));
						$q->stmt->execute();
						unset($q);
					}
					elseif( in_array( $po_rows[$a]['value'], $option_properties ) ) {
						unset( $option_properties[ array_search( $po_rows[$a]['value'], $option_properties ) ] );
					}
				}
			}

			for( $a=0; $a<count($option_properties); $a++ )
			{
				if( !empty($option_properties[$a]) )
				{
					$po_obj = $this->modx->newObject( $data['o']['class'][2], array(
						'product_id'	=> $product['id'],
						'key'			=> 'gifts'. $option['key'],
						'value'			=> $option_properties[$a],
					) );
					$po_obj->save();
					unset($po_obj);
				}
			}
		}
		/* << Привязываем значения опции к товару */


		$this->all_options_i++;

		return true;
	}
	/* << Работа с массивом связей разделов и товаров */


	/* >> Работа со связями товаров */
	public function synchProductsLinks( $v=array(), $row=array(), $data=array() )
	{
		if( !count($v) || !count($row) || !count($data) ) { return; }
		//return array('return'=>$array);

		$product_links_master_array=array();
		$product_links_slave_array=array();

		if( !empty($this->color_link_id) )
		{
			// >> получаем список связей с данным товаром и собираем в массив
			$q = $this->modx->newQuery( $data['pl']['class'][0] );
			$q->select( array_merge(
				array(
					$data['pl']['class'][0] .".master",
					$data['pl']['class'][0] .".slave",
				)
			) );
			$q->where( array(
					$data['pl']['class'][0] .'.link = '. $this->color_link_id .''.
					' AND '.
					'('.
						$data['pl']['class'][0] .'.master = '. $row['id'] .''.
						' OR '.
						$data['pl']['class'][0] .'.slave = '. $row['id'] .''.
					')'.
				'')
			);
			$s = $q->prepare(); //return array('return'=>$q->toSQL());
			$s->execute();
			$pl_rows = $s->fetchAll(PDO::FETCH_ASSOC);
			unset($q);
			unset($s);
			//return array('return'=>print_r($pl_rows,true));

			foreach( $pl_rows as $pl )
			{
				if( !in_array( $pl['master'], $product_links_master_array ) )
				{
					$product_links_master_array[] = $pl['master'];
				}

				if( !in_array( $pl['slave'], $product_links_slave_array ) )
				{
					$product_links_slave_array[] = $pl['slave'];
				}
			}
			// << получаем список связей с данным товаром и собираем в массив


			// >> получаем список товаров с таким же полем "group"
			$q = $this->modx->newQuery( $data['p']['class'][0] );
			$q->select( array_merge(
				array(
					$data['p']['class'][0] .".id",
				)
			) );
			$q->where( array(
					$data['p']['class'][0] .'.properties LIKE "%\"group\":\"'. $v['group'] .'\"%"'.
					' AND '.
					$data['p']['class'][0] .'.id != '. $row['id'] .''.
				'')
			);
			$s = $q->prepare(); //return array('return'=>$q->toSQL());
			$s->execute();
			$p_rows = $s->fetchAll(PDO::FETCH_ASSOC);
			unset($q);
			unset($s);
			//return array('return'=>print_r($p_rows,true));
			// << получаем список товаров с таким же полем "group"


			foreach( $p_rows as $p )
			{
				if( $p['id'] != $row['id'] )
				{
					if( !in_array( $p['id'], $product_links_master_array ) )
					{
						$pl_obj = $this->modx->newObject( $data['pl']['class'][0] );

						$pl_obj->set('link',	$this->color_link_id);
						$pl_obj->set('master',	$p['id']);
						$pl_obj->set('slave',	$row['id']);

						$pl_obj->save();

						unset($pl_obj);
					}

					if( !in_array( $p['id'], $product_links_slave_array ) )
					{
						$pl_obj = $this->modx->newObject( $data['pl']['class'][0] );

						$pl_obj->set('link',	$this->color_link_id);
						$pl_obj->set('master',	$row['id']);
						$pl_obj->set('slave',	$p['id']);

						$pl_obj->save();

						unset($pl_obj);
					}
				}
			}

		}
	}
	/* << Работа со связями товаров */


	/* >> Работа с файлами (загрузка к себе, обработка ms2Gallery) */
	public function synchProductsFiles( $array=array(), $row=array(), $data=array() )
	{
		if( !count($array) || !count($data) || !count($row) ) { return; }

		if( !isset($array[0]) ) {
			$v[0] = $array;
		} else {
			$v = $array;
		}

		$product_files = array();
		$product_files_name = array();

		$tmpPath = $this->assetsPath . 'tmp/'; // путь до папки со временными файлами

		// если папки нет - создадим
		if(	!file_exists( $tmpPath ) ) {
			mkdir( $tmpPath );
		}

		// >> обходим ограничение на загрузку 5 файлов в секунду
		if( $this->img_count_load >= 5 )
		{
			sleep(1);

			$this->img_count_load = 0;
			$this->img_count_load_time = time();
		}
		// << обходим ограничение на загрузку 5 файлов в секунду


		/* >> получаем список загруженных файлов у товара и формируем из этого массив */
		$q = $this->modx->newQuery( $data['pf']['class'][0] );
		$q->select( array_merge(
			array(
				$data['pf']['class'][0] .".id",
				$data['pf']['class'][0] .".name",
			)
		) );
		$q->where( array(
				$data['pf']['class'][0] .'.product_id = '. $row['id'] .''.
				' AND '.
				$data['pf']['class'][0] .'.source = '. $data['source_default'] .''.
				' AND '.
				$data['pf']['class'][0] .'.parent = 0'.
			'')
		);
		$s = $q->prepare(); //return array('return'=>$q->toSQL());
		$s->execute();
		$pf_rows = $s->fetchAll(PDO::FETCH_ASSOC);
		unset($q);
		unset($s);
		//return print_r($pf_rows,true);

		foreach( $pf_rows as $pf_row )
		{
			$product_files[] = array(
				'id'	=> $pf_row['id'],
			);
			$product_files_name[] = $pf_row['name'];
		}
		//return print_r($product_files,true);
		/* << получаем список загруженных файлов у товара и формируем из этого массив */


		/* >> обрабатываем список файлов товара (формируем список устаревших файлов для удаления) */
		foreach( $v as $attach )
		{
			$filename = str_replace( '/', '_', $attach['image'] );

			if( in_array( $filename, $product_files_name ) )
			{
				unset( $product_files[ array_search( $filename, $product_files_name ) ] );
			}
		}
		/* << обрабатываем список файлов товара (формируем список устаревших файлов для удаления) */


		/* >> получаем список файлов у товара с неверным source */
		$q = $this->modx->newQuery( $data['pf']['class'][0] );
		$q->select( array_merge(
			array(
				$data['pf']['class'][0] .".id",
			)
		) );
		$q->where( array(
				$data['pf']['class'][0] .'.product_id = '. $row['id'] .''.
				' AND '.
				$data['pf']['class'][0] .'.source != '. $data['source_default'] .''.
				' AND '.
				$data['pf']['class'][0] .'.parent = 0'.
			'')
		);
		$s = $q->prepare(); //return array('return'=>$q->toSQL());
		$s->execute();
		$pfs_rows = $s->fetchAll(PDO::FETCH_ASSOC);
		unset($q);
		unset($s);
		//return print_r($pfs_rows,true);

		foreach( $pfs_rows as $pfs_row )
		{
			$files_for_remove[] = $pfs_row['id'];
		}
		//return print_r($files_for_remove,true);
		/* << получаем список файлов у товара с неверным source */


		/* >> удаляем устаревшие файлы */
		for( $a=0; $a<count($product_files); $a++ )
		{
			if( !empty($product_files[$a]['id']) )
			{
				$files_for_remove[] = $product_files[$a]['id'];
			}
		}

		if( count($files_for_remove) > 0 )
		{
			$response_remove = $this->modx->runProcessor('gallery/remove_multiple', array(
					'ids' => implode(',', $files_for_remove),
					'product_id' => $row['id'],
				),
				array('processors_path' => MODX_CORE_PATH.'components/minishop2/processors/mgr/')
			);
		}
		/* << удаляем устаревшие файлы */


		/* >> обрабатываем список файлов товара (скачиваем к себе и закачиваем в minishop2) */
		foreach( $v as $attach )
		{
			$return = $this->restartScriptIfTimeIsRunningOut( $data['iteration_key'], $data['max_iterations'] );

			if( $return['restart'] )
			{
				return $return;
			}

			$filename = str_replace( '/', '_', $attach['image'] );

			if( !in_array( $filename, $product_files_name ) )
			{
				$file = $this->synchProductsFileDownload( $attach['image'] , $tmpPath ); // качаем к себе

				// заливаем в минишоп
				$response_upload = $this->modx->runProcessor('gallery/upload', array(
						'file' => $file,
						'id' => $row['id'],
					),
					array('processors_path' => MODX_CORE_PATH.'components/minishop2/processors/mgr/')
				);

				unlink( $file ); // удаляем временный файл
			}
		}
		/* << обрабатываем список файлов товара (скачиваем к себе и закачиваем в minishop2) */


		return true;
	}
	/* << Работа с файлами (загрузка к себе, обработка ms2Gallery) */


	/* >> Сортируем фотки товара - назначаем нужное главное изображение */
	public function synchProductsFilesSort( $str='', $row=array(), $data=array() )
	{
		if( $str == '' || empty($row) || empty($data) ) { return; }

		$filename = str_replace( '/', '_', $str ); // получаем имя файла с расширением

		$filename = substr( $filename, 0, strrpos($filename, '_') );

		/*if( $slash_pos = strrpos($str, '/') )
		{
			$str = str_replace( '/', '', substr( $str, $slash_pos ) );
		}*/


		/* >> получаем файлы для проверки их rank */
		$q = $this->modx->newQuery( $data['pf']['class'][0] );
		$q->select( array_merge(
			array(
				$data['pf']['class'][0] .".id",
				$data['pf']['class'][0] .".rank",
				$data['pf']['class'][0] .".name",
			)
		) );
		$q->where( array(
				$data['pf']['class'][0] .'.product_id = '. $row['id'] .''.
				' AND '.
				$data['pf']['class'][0] .'.source = '. $data['source_default'] .''.
				' AND '.
				$data['pf']['class'][0] .'.parent = 0'.
			'')
		);
		$s = $q->prepare(); //return array('return'=>$q->toSQL());
		$s->execute();
		$pfs_rows = $s->fetchAll(PDO::FETCH_ASSOC);
		unset($q);
		unset($s);
		//return print_r($pfs_rows,true);

		$nosort = false;

		foreach( $pfs_rows as $pfs_row )
		{
			if( strstr($pfs_row['name'], $filename) )
			{
				if( $pfs_row['rank'] == '0' )
				{
					$nosort = true;
					break;
				}
				else {
					$source = $pfs_row['id'];
				}
			}

			if( $pfs_row['rank'] == '1' )
			{
				$target = $pfs_row['id'];
			}
		}
		//return print_r($files_for_remove,true);
		/* << получаем файлы для проверки их rank */


		if( !$nosort )
		{
			$response = $this->modx->runProcessor('gallery/sort', array(
					'product_id' => $row['id'],
					'source' => $source,
					'target' => $target,
				),
				array('processors_path' => MODX_CORE_PATH.'components/minishop2/processors/mgr/')
			);
		}

	}
	/* << Сортируем фотки товара - назначаем нужное главное изображение */


	/* >> Скачиваем файл */
	public function synchProductsFileDownload( $from='', $tmpPath='' )
	{
		if( empty($from) || empty($tmpPath) ) { return; }

		$filename = str_replace( '/', '_', $from ); // получаем имя файла с расширением

		$to = $tmpPath . $filename;

		$fp = fopen( $to , 'w+b');

		$ch = curl_init( $this->url_gifts_api . $from );
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		curl_close($ch);

		fclose($fp);

		unset($fp);
		unset($ch);

		// для обхода ограничения в 5 загрузок в секунду
		if( $this->img_count_load_time != time() )
		{
			$this->img_count_load = 0;
			$this->img_count_load_time = time();
		}

		$this->img_count_load++;

		return $to;
	}
	/* << Скачиваем файл */


	/* >> Убираем с публикации отсутствующие в выгрузке товары */
	public function synchProductsUnpublish( $data=array() )
	{
		if( !count($data) ) { return; }

		$q = $this->modx->newQuery( $data['p']['class'][0] );
		$q->command('update');
		$q->set(array(
		    'published'		=> 0,
		    'publishedon'	=> 0,
		    'publishedby'	=> 0,
		));
		$q->where(array(
			'editedon < '. $this->start_time .''.
			' AND '.
			'isfolder = 0'.
			' AND '.
			'deleted = 0'.
			' AND '.
			'class_key = "'. $data['p']['class'][0] .'"'.
			' AND '.
			'context_key = "'. $data['context'] .'"'.
			' AND '.
			'properties LIKE "%\"product_id\":\"%"'.
			/*' AND '.
			$data['p']['class'][0] .'.parent = '. $this->array_categories['1']['id'] .''.*/
		''));
		$q->prepare();
		//return $q->toSQL();
		$q->stmt->execute();

		return true;
	}
	/* << Убираем с публикации отсутствующие в выгрузке товары */


	public function convertPrice( $price=0, $data=array() )
	{
		if( !count($data) ) { return; }
		if( empty($price) ) { return $price; }

		$new_price = $price;

		if( !empty($data['multiply']) ) {
			$new_price = ( $price * $data['multiply'] );
		}
		elseif( !empty($data['divide']) ) {
			$new_price = ( $price / $data['divide'] );
		}
		$new_price = round( $new_price );

		return $new_price;
	}


	public function convertBytes( $number )
	{
		$len = strlen($number);

		if( $len < 4 )
		{
			return sprintf("%d b", $number);
		}

		if( $len >= 4 && $len <= 6 )
		{
			return sprintf("%0.2f Kb", $number/1024);
		}

		if( $len >= 7 && $len <= 9 )
		{
			return sprintf("%0.2f Mb", $number/1024/1024);
		}

		return sprintf("%0.2f Gb", $number/1024/1024/1024);
	}


	/* >> Из объекта в массив */
	public function object2array( $xmlObject, $out = array() )
	{
		foreach( (array)$xmlObject as $index => $node )
		{
			if( is_object($node) || is_array($node) )
			{
				$out[$index] = $this->object2array( $node );
			}
			else {
				$out[$index] = (string)$node;
			}

			unset($node);
		}
		unset($xmlObject);
		return $out;
	}
	/* << Из объекта в массив */

}

return 'msGiftsRuSynchItemSynchronizationProcessor';