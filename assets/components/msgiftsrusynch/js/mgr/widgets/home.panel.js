msGiftsRuSynch.panel.Home = function (config) {
	config = config || {};
	Ext.apply(config, {
		baseCls: 'modx-formpanel',
		layout: 'anchor',
		/*
		 stateful: true,
		 stateId: 'msgiftsrusynch-panel-home',
		 stateEvents: ['tabchange'],
		 getState:function() {return {activeTab:this.items.indexOf(this.getActiveTab())};},
		 */
		hideMode: 'offsets',
		items: [{
			html: '<h2>' + _('msgiftsrusynch') + '</h2>',
			cls: '',
			style: {margin: '15px 0'}
		}, {
			xtype: 'modx-tabs',
			defaults: {border: false, autoHeight: true},
			border: true,
			hideMode: 'offsets',
			items: [{
				title: _('msgiftsrusynch_items'),
				layout: 'anchor',
				items: [{
					html: _('msgiftsrusynch_intro_msg'),
					cls: 'panel-desc',
				}, {
					xtype: 'msgiftsrusynch-grid-items',
					cls: 'main-wrapper',
				}]
			}]
		}]
	});
	msGiftsRuSynch.panel.Home.superclass.constructor.call(this, config);
};
Ext.extend(msGiftsRuSynch.panel.Home, MODx.Panel);
Ext.reg('msgiftsrusynch-panel-home', msGiftsRuSynch.panel.Home);
