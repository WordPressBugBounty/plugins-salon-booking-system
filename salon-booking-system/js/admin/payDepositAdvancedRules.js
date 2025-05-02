jQuery(function ($) {

    const rulesMetabox = $('#sln-pay_deposit_advanced_rules');
    if (!rulesMetabox.length) return;

    const rulesContainer = $('.ar-rules');

    let ruleHtml;

    function init() {
        // cache and remove form rules html templates
        const ruleNode = $('[data-id=__template__]');
        ruleHtml = ruleNode.prop('outerHTML');
        ruleNode.remove();

        bindRulesHandlers();
        addFormRule();
    }

    function addFormRule() {
        $('[data-action=ar-rule-add]').click(function () {
            const rules = $('.ar-rule');
            const lastRule = rules.last();
            const newRuleId = lastRule.length ? parseInt(lastRule.attr('data-id')) + 1 : 0;

            let html = ruleHtml
                .replace(/__template__/g, newRuleId)
                .replace(/hide/, '')
                .replace(/sln-select-template/, 'sln-select');
            rulesContainer.append(html);

            sln_createSelect2Full($);
            sln_initDatepickers($);
            bindRulesHandlers();
        });
    }

    function removeFormRule(button) {
        const rule = button.closest('.ar-rule');
        rule.remove();
    }

    function bindRulesHandlers() {
        $('.ar-rule').each(function () {
            const rule = $(this);
            const deleteButton = rule.find('[data-action=ar-rule-remove]');
            deleteButton.click(() => removeFormRule(deleteButton));
        });
    }

    init();
});
