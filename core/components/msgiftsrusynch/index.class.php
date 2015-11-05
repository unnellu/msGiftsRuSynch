<?php

/**
 * Class msGiftsRuSynchMainController
 */
abstract class msGiftsRuSynchMainController extends modExtraManagerController {
	/** @var msGiftsRuSynch $msGiftsRuSynch */
	public $msGiftsRuSynch;
	/** @var miniShop2 $miniShop2 */
	public $miniShop2;


	/**
	 * @return void
	 */
	public function initialize() {
		$corePath = $this->modx->getOption('msgiftsrusynch_core_path', null, $this->modx->getOption('core_path') . 'components/msgiftsrusynch/');
		require_once $corePath . 'model/msgiftsrusynch/msgiftsrusynch.class.php';
		
		if(!include_once MODX_CORE_PATH . 'components/minishop2/model/minishop2/minishop2.class.php') {
			throw new Exception('You must install miniShop2 first');
		}
		
		$version = $this->modx->getVersionData();
		$modx23 = !empty($version) && version_compare($version['full_version'], '2.3.0', '>=');
		if (!$modx23) {
			$this->addCss(MODX_ASSETS_URL . 'components/msearch2/css/mgr/font-awesome.min.css');
		}
		
		$this->msGiftsRuSynch = new msGiftsRuSynch($this->modx);
		$this->miniShop2 = new miniShop2($this->modx);
		
		$this->addCss($this->msGiftsRuSynch->config['cssUrl'] . 'mgr/main.css');
		$this->addJavascript($this->msGiftsRuSynch->config['jsUrl'] . 'mgr/msgiftsrusynch.js');
		
		$this->addJavascript($this->miniShop2->config['jsUrl'] . 'mgr/minishop2.js');
		$this->addJavascript($this->miniShop2->config['jsUrl'] . 'mgr/misc/ms2.utils.js');
		$this->addJavascript($this->miniShop2->config['jsUrl'] . 'mgr/misc/ms2.combo.js');
		
		$this->addHtml('<script type="text/javascript">
			MODx.modx23 = ' . (int)$modx23 . ';
			miniShop2.config = ' . $this->modx->toJSON($this->miniShop2->config) . ';
			miniShop2.config.connector_url = "' . $this->miniShop2->config['connectorUrl'] . '";
			msGiftsRuSynch.config = ' . $this->modx->toJSON($this->msGiftsRuSynch->config) . ';
			msGiftsRuSynch.config.connector_url = "' . $this->msGiftsRuSynch->config['connectorUrl'] . '";
		</script>');
		
		parent::initialize();
	}


	/**
	 * @return array
	 */
	public function getLanguageTopics() {
		return array('msgiftsrusynch:default');
	}


	/**
	 * @return bool
	 */
	public function checkPermissions() {
		return true;
	}
}


/**
 * Class IndexManagerController
 */
class IndexManagerController extends msGiftsRuSynchMainController {

	/**
	 * @return string
	 */
	public static function getDefaultController() {
		return 'home';
	}
}
