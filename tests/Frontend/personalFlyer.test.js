import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createPersonalFlyer } from '../../resources/js/utils/personalFlyer.js';

function canvasBrowser(t, { failedImage = null, failedExport = false, width = 1024, height = 1536 } = {}) {
    const previousImage = globalThis.Image;
    const previousDocument = globalThis.document;
    const loadedImages = [];
    const drawing = [];
    const blob = new Blob(['personal flyer'], { type: 'image/png' });
    const context = {
        drawImage: (...args) => drawing.push(['image', ...args]),
        fillRect: (...args) => drawing.push(['fill', ...args]),
    };
    const canvas = {
        getContext: () => context,
        toBlob(callback, type) { assert.equal(type, 'image/png'); callback(failedExport ? null : blob); },
    };
    globalThis.Image = class {
        naturalWidth = width;
        naturalHeight = height;
        set src(value) {
            this.source = value;
            loadedImages.push(this);
            queueMicrotask(() => value === failedImage ? this.onerror() : this.onload());
        }
    };
    globalThis.document = { createElement: name => { assert.equal(name, 'canvas'); return canvas; } };
    t.after(() => { globalThis.Image = previousImage; globalThis.document = previousDocument; });
    return { loadedImages, drawing, canvas, context, blob };
}

test('the personal PNG retains the flyer and replaces only the existing lower-right QR with a quiet zone', async (t) => {
    const browser = canvasBrowser(t);

    const result = await createPersonalFlyer('/assets/event/Flyer 2026 stream.png', 'data:image/svg+xml;base64,personal');

    assert.equal(result, browser.blob);
    assert.deepEqual(browser.loadedImages.map(image => image.source), ['/assets/event/Flyer 2026 stream.png', 'data:image/svg+xml;base64,personal']);
    assert.equal(browser.canvas.width, 1024);
    assert.equal(browser.canvas.height, 1536);
    assert.deepEqual(browser.drawing, [
        ['image', browser.loadedImages[0], 0, 0, 1024, 1536],
        ['fill', 861, 1209, 130, 130],
        ['image', browser.loadedImages[1], 871, 1219, 110, 110],
    ]);
    assert.equal(browser.context.fillStyle, '#ffffff');
    assert.equal(browser.context.imageSmoothingEnabled, false);
});

test('the QR replacement scales with the original flyer resolution', async (t) => {
    const browser = canvasBrowser(t, { width: 2048, height: 3072 });

    await createPersonalFlyer('/flyer.png', '/personal.svg');

    assert.deepEqual(browser.drawing[1], ['fill', 1722, 2418, 260, 260]);
    assert.deepEqual(browser.drawing[2], ['image', browser.loadedImages[1], 1743, 2439, 218, 218]);
});

test('an unreadable QR rejects generation instead of returning the original non-personal flyer', async (t) => {
    const browser = canvasBrowser(t, { failedImage: '/personal.svg' });

    await assert.rejects(createPersonalFlyer('/flyer.png', '/personal.svg'), /kon niet worden geladen/);

    assert.equal(browser.drawing.length, 0);
});

test('a failed PNG export reports an error instead of offering an empty download', async (t) => {
    canvasBrowser(t, { failedExport: true });

    await assert.rejects(createPersonalFlyer('/flyer.png', '/personal.svg'), /kon niet worden opgeslagen/);
});
