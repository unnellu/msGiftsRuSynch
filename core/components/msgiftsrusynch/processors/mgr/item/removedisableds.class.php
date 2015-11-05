<?php

/**
 * Remove an Items
 */
class msGiftsRuSynchItemRemoveDisabledsProcessor extends modObjectProcessor {
	public $objectType = 'msGiftsRuSynchItem';
	public $classKey = 'msGiftsRuSynchItem';
	public $languageTopics = array('msgiftsrusynch');
	//public $permission = 'remove';


	/**
	 * @return array|string
	 */
	public function process()
	{
		$assetsPath = $this->modx->getOption('msgiftsrusynch_assets_path', null, $this->modx->getOption('assets_path') . 'components/msgiftsrusynch/');

		if(!$this->checkPermissions()) {
			return $this->failure($this->modx->lexicon('access_denied'));
		}

		//return $this->failure( $this->classKey );

		if( $objects_array = $this->modx->getCollection($this->classKey, array( 'active'=>'0' )) )
		{
			/** @var msGiftsRuSynchItem $object */
			foreach( $objects_array as $object )
			{
				$xmlPath = $assetsPath . 'xml_files/' . $object->get('id') . '/';
				$logPath = $assetsPath . 'logs/' . $object->get('id') . '/';

				$this->removeDir($xmlPath);
				$this->removeDir($logPath);

				$object->remove();
			}
		}
		else {
			return $this->failure($this->modx->lexicon('msgiftsrusynch_item_err_nf'));
		}

		return $this->success();
	}


	private function removeDir($path)
	{
		if($objs = glob( $path . "*" ) )
		{
			foreach($objs as $obj)
			{
				is_dir($obj) ? $this->removeDir($obj) : unlink($obj);
			}
		}

		rmdir($path);
	}

}

return 'msGiftsRuSynchItemRemoveDisabledsProcessor';