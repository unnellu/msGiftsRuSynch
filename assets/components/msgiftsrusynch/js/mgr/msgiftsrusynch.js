var msGiftsRuSynch = function (config) {
	config = config || {};
	msGiftsRuSynch.superclass.constructor.call(this, config);
};
Ext.extend(msGiftsRuSynch, Ext.Component, {
	page: {}, window: {}, grid: {}, tree: {}, panel: {}, combo: {}, config: {}, view: {}, utils: {}
});
Ext.reg('msgiftsrusynch', msGiftsRuSynch);

msGiftsRuSynch = new msGiftsRuSynch();