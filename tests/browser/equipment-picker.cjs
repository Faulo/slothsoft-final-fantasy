// Run inside DDEV against a host configured with the private US ROM and save.
const assert = require('node:assert/strict');
const {firefox} = require(process.env.FF_PLAYWRIGHT_MODULE || 'playwright');

(async () => {
    const browser = await firefox.launch({headless: true});
    try {
        const page = await browser.newPage({ignoreHTTPSErrors: true, viewport: {width: 1500, height: 1000}});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const url = process.env.FF_EDITOR_URL || 'https://slothsoft.ddev.site/FinalFantasy/I/SaveEditor/';
        const loadSave = async (target, waitForFont = true) => {
            await target.goto(url);
            await target.locator('#ff-save').setInputFiles(process.env.FF_SAVE_PATH);
            await Promise.all([target.waitForNavigation(), target.getByRole('button', {name: 'Load save'}).click()]);
            if (waitForFont) {
                await target.evaluate(() => document.fonts.ready);
            }
        };
        await loadSave(page);
        assert.equal(await page.locator('.ff-combobox, .ff-choice').count(), 0);
        assert.equal(await page.locator('.ff-item-picker select:visible').count(), 32);
        const select = page.locator('#ff-0-weapons-0');
        assert.equal(await select.locator('option').count(), 41);
        await select.selectOption('1');
        const font = await select.locator('option[value="1"]').evaluate(node => ({
            family: getComputedStyle(node).fontFamily,
            background: getComputedStyle(node).backgroundColor,
            text: node.textContent,
            loaded: document.fonts.check('16px "Final Fantasy I"')
        }));
        assert.match(font.family, /Final Fantasy I/);
        assert.equal(font.background, 'rgb(0, 0, 252)');
        assert.equal(font.text, 'Wooden\u{1F962}');
        assert.equal(font.loaded, true);
        // Browser rasterization must use the original tile for every Unicode alias,
        // including emoji that could otherwise fall back to a color emoji font.
        const aliasesMatch = await page.evaluate(() => {
            const aliases = [[0x1F5E1, 0xD4], [0x1F528, 0xD5], [0x1F52A, 0xD6],
                [0x1FA93, 0xD7], [0x1FA84, 0xD8], [0x1F962, 0xD9],
                [0x1F9BA, 0xDA], [0x26E8, 0xDB], [0x1FA96, 0xDC],
                [0x1F9E4, 0xDD], [0x1F48D, 0xDE], [0x1F455, 0xDF], [0x1F9F4, 0xE1]];
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = 64;
            const context = canvas.getContext('2d');
            context.font = '32px "Final Fantasy I"';
            const pixels = code => {
                context.clearRect(0, 0, 64, 64);
                context.fillText(String.fromCodePoint(code), 0, 32);
                return Array.from(context.getImageData(0, 0, 64, 64).data);
            };
            return aliases.every(([code, tile]) => {
                const expected = pixels(0xE000 + tile);
                const actual = pixels(code);
                return expected.some(value => value !== 0) && actual.every((value, index) => value === expected[index]);
            });
        });
        assert.equal(aliasesMatch, true, 'Unicode icons must render the same pixels as their ROM tiles');
        await select.click();
        if (process.env.FF_SCREENSHOT) {
            await page.screenshot({path: process.env.FF_SCREENSHOT});
        }
        await select.press('Escape');
        await select.selectOption('4');
        assert.equal(await select.inputValue(), '4');
        assert.equal(await select.evaluate(node => new FormData(node.form).get(node.name)), '4');
        await select.focus();
        await select.press('End');
        await select.press('Enter');
        assert.equal(await select.inputValue(), '40');
        const fallback = await browser.newPage({ignoreHTTPSErrors: true, javaScriptEnabled: false});
        await loadSave(fallback, false);
        assert.equal(await fallback.locator('#ff-0-weapons-0').isVisible(), true);
        assert.equal(await fallback.locator('#ff-0-weapons-0 option[value="1"]').textContent(), 'Wooden\u{1F962}');
        assert.deepEqual(errors, []);
        console.log('Native Firefox equipment picker: font, palette, glyphs, selection, form values and no-JavaScript behavior passed.');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
