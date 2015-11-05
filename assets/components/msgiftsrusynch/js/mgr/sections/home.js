msGiftsRuSynch.page.Home = function (config) {
	config = config || {};
	Ext.applyIf(config, {
		components: [{
			xtype: 'msgiftsrusynch-panel-home',
			renderTo: 'msgiftsrusynch-panel-home-div'
		}]
	});
	msGiftsRuSynch.page.Home.superclass.constructor.call(this, config);
};
Ext.extend(msGiftsRuSynch.page.Home, MODx.Component);
Ext.reg('msgiftsrusynch-page-home', msGiftsRuSynch.page.Home);