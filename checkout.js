import 'blockchyp';

const settings = window.wc.wcSettings.getSettings('blockchyp_data', {});
console.log(settings);
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || window.wp.i18n.__('BlockChyp', 'blockchyp');
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '');
};

const Block_Gateway = {
    name: 'blockchyp', 
    label: label,
    content: Object(window.wp.element.createElement)(Content, null),
    edit: Object(window.wp.element.createElement)(Content, null),
    canMakePayment: () => true, 
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod( Block_Gateway );