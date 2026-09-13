import test from 'node:test';
import assert from 'node:assert/strict';
import { nextEnabledOption, selectMenuPosition } from '../../resources/js/Support/selectMenu.js';

test('keyboard navigation skips disabled placeholders and wraps in both directions', () => {
    const options = [{ disabled: true }, {}, { disabled: true }, {}];
    assert.equal(nextEnabledOption(options, -1, 1), 1);
    assert.equal(nextEnabledOption(options, 1, 1), 3);
    assert.equal(nextEnabledOption(options, 3, 1), 1);
    assert.equal(nextEnabledOption(options, 1, -1), 3);
});
test('an empty or fully disabled menu never loops indefinitely', () => {
    assert.equal(nextEnabledOption([], 0, 1), -1);
    assert.equal(nextEnabledOption([{ disabled: true }], 0, -1), -1);
});
test('desktop menu aligns to its field and caps long-list height', () => {
    const position = selectMenuPosition({ left: 300, top: 200, bottom: 240, width: 280 }, { width: 1440, height: 900 });
    assert.deepEqual(position, { left: 300, width: 280, maxHeight: 360, top: 246, bottom: null });
});
test('menus near the bottom open upwards without extending below the screen', () => {
    const position = selectMenuPosition({ left: 260, top: 700, bottom: 740, width: 260 }, { width: 800, height: 800 });
    assert.equal(position.top, null);
    assert.equal(position.bottom, 106);
    assert.equal(position.maxHeight, 360);
});
test('wide fields and right-edge fields remain inside a 360px viewport', () => {
    for (const rect of [{ left: 12, top: 120, bottom: 164, width: 560 }, { left: 290, top: 120, bottom: 164, width: 60 }]) {
        const position = selectMenuPosition(rect, { width: 360, height: 800 });
        assert.ok(position.left >= 8);
        assert.ok(position.left + position.width <= 352);
    }
});
test('short viewport constrains the list instead of imposing an overflowing minimum', () => {
    const position = selectMenuPosition({ left: 8, top: 80, bottom: 124, width: 180 }, { width: 360, height: 200 });
    assert.ok(position.maxHeight <= 66);
});
