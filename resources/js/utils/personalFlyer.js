function loadImage(source) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('De flyer of QR-code kon niet worden geladen. Probeer het opnieuw.'));
        image.crossOrigin = 'anonymous';
        image.src = source;
    });
}

export async function createPersonalFlyer(templateUrl, donationQrImage) {
    const [template, qr] = await Promise.all([loadImage(templateUrl), loadImage(donationQrImage)]);
    const canvas = document.createElement('canvas');
    canvas.width = template.naturalWidth;
    canvas.height = template.naturalHeight;
    const context = canvas.getContext('2d');
    if (!context || !canvas.width || !canvas.height) {
        throw new Error('Je browser kan de persoonlijke flyer niet maken. Probeer een andere browser.');
    }

    context.drawImage(template, 0, 0, canvas.width, canvas.height);

    // Replace the printed QR inside its existing white frame on the 1024 × 1536 source flyer.
    const x = Math.round(canvas.width * 861 / 1024);
    const y = Math.round(canvas.height * 1209 / 1536);
    const size = Math.round(canvas.width * 130 / 1024);
    const quietZone = Math.round(size * 0.08);
    context.fillStyle = '#ffffff';
    context.fillRect(x, y, size, size);
    context.imageSmoothingEnabled = false;
    context.drawImage(qr, x + quietZone, y + quietZone, size - quietZone * 2, size - quietZone * 2);

    return new Promise((resolve, reject) => {
        canvas.toBlob(blob => {
            if (blob) resolve(blob);
            else reject(new Error('De flyer kon niet worden opgeslagen. Probeer het opnieuw.'));
        }, 'image/png');
    });
}
