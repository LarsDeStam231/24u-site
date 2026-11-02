export function createPasskeyLoginPoller({ consume, onState, onApproved, onFailure, schedule = globalThis.setTimeout, cancel = globalThis.clearTimeout }) {
    let generation = 0;
    let active = false;
    let timer;
    let inFlight;
    let failures = 0;
    let backingOff = false;

    function stop() {
        active = false;
        generation++;
        backingOff = false;
        cancel(timer);
    }

    function fail(message, recoverable = false) {
        active = false;
        onState('failed');
        onFailure({ message, recoverable });
    }

    async function check() {
        if (!active || backingOff || inFlight === generation) return;
        const current = generation;
        inFlight = current;
        cancel(timer);

        try {
            const result = await consume();
            if (!active || current !== generation) return;

            if (result.status === 'approved' && typeof result.redirect === 'string' && result.redirect) {
                active = false;
                onState('approved');
                onApproved(result.redirect);
                return;
            }

            if (result.status !== 'pending') {
                fail('Het inloggen kon niet worden afgerond. Maak een nieuwe QR-code.');
                return;
            }

            failures = 0;
            onState('waiting');
            timer = schedule(check, 2000);
        } catch (failure) {
            if (!active || current !== generation) return;
            const status = failure.response?.status;
            const temporary = !status || status === 408 || status === 429 || status >= 500;

            if (temporary && ++failures <= 5) {
                onState('retrying');
                const retryAfter = Number(failure.response?.headers?.['retry-after']);
                const delay = retryAfter > 0 ? retryAfter * 1000 : Math.min(2000 * 2 ** (failures - 1), 10000);
                backingOff = true;
                timer = schedule(() => {
                    backingOff = false;
                    return check();
                }, delay);
                return;
            }

            if (temporary) {
                fail('De verbinding met de computer kon niet worden hersteld. Probeer het afronden opnieuw; je hoeft geen wachtwoord in te voeren.', true);
            } else if (status === 401 || status === 419) {
                fail('De computersessie is verlopen. Ververs deze pagina en start een nieuwe QR-inlog.');
            } else if (status === 410) {
                fail('Deze QR-code is verlopen of ingetrokken. Maak een nieuwe QR-code.');
            } else {
                fail(failure.response?.data?.message || 'De QR-inlog kon niet worden afgerond. Maak een nieuwe QR-code.');
            }
        } finally {
            if (inFlight === current) inFlight = undefined;
        }
    }

    function start() {
        stop();
        active = true;
        failures = 0;
        onState('waiting');
        return check();
    }

    return { start, check, stop };
}
