// Run against a host with the module installed and a privately supplied US ROM/save.
const assert = require('node:assert/strict');
const {chromium} = require(process.env.FF_PLAYWRIGHT_MODULE || 'playwright');

(async () => {
    const browser = await chromium.launch({channel: process.env.FF_BROWSER_CHANNEL || 'msedge', headless: true});
    try {
        const page = await browser.newPage({ignoreHTTPSErrors: true});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const url = process.env.FF_EDITOR_URL || 'https://slothsoft.ddev.site/FinalFantasy/I/SaveEditor/';
        const loadSave = async target => {
            await target.goto(url);
            await target.locator('#ff-save').setInputFiles(process.env.FF_SAVE_PATH);
            await Promise.all([target.waitForNavigation(), target.getByRole('button', {name: 'Load save'}).click()]);
        };
        await loadSave(page);
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.locator('.ff-choice').count(), 32);
        const select = page.locator('#ff-0-weapons-0');
        const button = page.locator('#ff-0-weapons-0-picker');
        const list = page.locator('#ff-0-weapons-0-options');
        const original = await select.inputValue();
        await select.selectOption('1');
        await button.click();
        assert.equal(await list.isVisible(), true);
        assert.equal(await list.getByRole('option').count(), 41);
        assert.equal(await list.locator('.ff-text').count(), 40);
        assert.match(await list.locator('.ff-text').first().evaluate(node => getComputedStyle(node).fontFamily), /Final Fantasy I/);
        assert.equal(await list.locator('.ff-text').first().textContent(), 'Wooden\uE0D9');
        if (process.env.FF_SCREENSHOT) {
            await page.screenshot({path: process.env.FF_SCREENSHOT});
        }
        await list.getByRole('option', {name: 'Rapier', exact: true}).click();
        assert.equal(await select.inputValue(), '4');
        assert.equal(await select.evaluate(node => new FormData(node.form).get(node.name)), '4');
        assert.equal(await list.isVisible(), false);
        await button.press('ArrowDown');
        await button.press('End');
        await button.press('Escape');
        assert.equal(await select.inputValue(), '4');
        await button.press('ArrowDown');
        await button.press('End');
        await button.press('Enter');
        assert.equal(await select.inputValue(), '40');
        await button.press('w');
        await button.press('o');
        await button.press('o');
        await button.press('d');
        await button.press('Enter');
        assert.equal(await select.inputValue(), '1');
        await button.press('Home');
        await button.press('Tab');
        assert.equal(await select.inputValue(), '0');
        assert.equal(await list.isVisible(), false);
        await page.locator('label[for="ff-0-weapons-0-picker"]').click();
        assert.equal(await button.evaluate(node => node === document.activeElement), true);
        await select.evaluate(node => node.form.reset());
        await page.waitForFunction(() => {
            const option = document.querySelector('#ff-0-weapons-0').selectedOptions[0];
            return document.querySelector('#ff-0-weapons-0-picker').textContent === (option.dataset.fontLabel || option.label);
        });
        assert.equal(await select.inputValue(), original);
        const fallback = await browser.newPage({ignoreHTTPSErrors: true, javaScriptEnabled: false});
        await loadSave(fallback);
        assert.equal(await fallback.locator('#ff-0-weapons-0').isVisible(), true);
        assert.equal((await fallback.locator('#ff-0-weapons-0 option[value="1"]').textContent()).trim(), 'Wooden nunchuck');
        assert.deepEqual(errors, []);
        console.log('Equipment picker: font, icons, mouse, keyboard, form values, reset and no-JavaScript fallback passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
