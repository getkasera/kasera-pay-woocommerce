/**
 * Registers Kasera Pay with the block checkout. Plain JS on purpose —
 * no JSX, no build step. The method only shows title + description;
 * payment is the server-side redirect flow.
 */
(function () {
    var el = window.wp.element.createElement;
    var decode = window.wp.htmlEntities.decodeEntities;
    var settings = window.wc.wcSettings.getSetting('kasera_pay_data', {});
    var title = decode(settings.title || 'Kasera Pay');

    var Content = function () {
        return el('span', null, decode(settings.description || ''));
    };

    window.wc.wcBlocksRegistry.registerPaymentMethod({
        name: 'kasera_pay',
        label: el('span', null, title),
        content: el(Content, null),
        edit: el(Content, null),
        canMakePayment: function () {
            return true;
        },
        ariaLabel: title,
        supports: {
            features: settings.supports || ['products'],
        },
    });
})();
