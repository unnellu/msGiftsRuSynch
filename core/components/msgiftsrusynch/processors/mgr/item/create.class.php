<?php

/**
 * Create an Item
 */
class msGiftsRuSynchItemCreateProcessor extends modObjectCreateProcessor {
	public $objectType = 'msGiftsRuSynchItem';
	public $classKey = 'msGiftsRuSynchItem';
	public $languageTopics = array('msgiftsrusynch');
	//public $permission = 'create';

	private $url_gifts_api = '';

	private $xmlFiles = array();


	public function initialize()
	{
		$this->xmlFiles = array(
			'treeWithoutProducts.xml',
			'stock.xml',
			'filters.xml',
			'catalogue.xml',
		);

		$api_user = $this->modx->getOption('msgiftsrusynch_api_user', null, '');
		$api_password = $this->modx->getOption('msgiftsrusynch_api_password', null, '');

		if( $api_user == '' || $api_password == '' )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_api_login');
		}

		$this->url_gifts_api = "http://".$api_user.":".$api_password."@api2.gifts.ru/export/v2/catalogue/";

		$api_page_content = file_get_contents( $this->url_gifts_api . $this->xmlFiles[0] );

		if( strstr( $api_page_content, '<title>401</title>' ) || strstr( $api_page_content, 'You can change' ) )
		{
			return $this->modx->lexicon('msgiftsrusynch_item_err_api_ip');
		}

		return parent::initialize();
	}


	/**
	 * @return bool
	 */
	public function beforeSet()
	{
		/*
		$name = trim($this->getProperty('name'));
		if (empty($name)) {
			$this->modx->error->addField('name', $this->modx->lexicon('msgiftsrusynch_item_err_name'));
		}
		elseif ($this->modx->getCount($this->classKey, array('name' => $name))) {
			$this->modx->error->addField('name', $this->modx->lexicon('msgiftsrusynch_item_err_ae'));
		}
		*/

		$date = date("Y-m-d H:i:s");
		$this->setProperty('date', $date);
		$this->setProperty('active', '1');

		return parent::beforeSet();
	}


	/**
	 * @return bool
	 */
	public function afterSave()
	{
		$assetsPath = $this->modx->getOption('msgiftsrusynch_assets_path', null, $this->modx->getOption('assets_path') . 'components/msgiftsrusynch/');

		$path = $assetsPath . 'xml_files/' . $this->object->get('id') . '/';
		mkdir( $path );

		for($i=0; $i<count($this->xmlFiles); $i++)
		{
			$ch = curl_init( $this->url_gifts_api . $this->xmlFiles[$i] );

			$fp = fopen( $path . $this->xmlFiles[$i] , 'w+b');
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);

			/*$this->object->set('description',
				$path . $this->xmlFiles[$i] .' '.
				$this->url_gifts_api . $this->xmlFiles[$i] .', '.
				$this->object->get('description')
			);
			$this->object->save();*/
		}

		return parent::afterSave();
	}

}

return 'msGiftsRuSynchItemCreateProcessor';
