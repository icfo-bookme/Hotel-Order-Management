import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const view = readFileSync(new URL('../../resources/views/ship_tickets/index.blade.php', import.meta.url), 'utf8');
const centsSource = view.slice(view.indexOf('function amountToCents('), view.indexOf('function formatMoney('));
const moneySource = view.slice(view.indexOf('function formatMoney('), view.indexOf('function getRowValues('));
const pricingSource = view.slice(view.indexOf('function calculateRoomTotals('), view.indexOf('function calculateBookingTotals('));
const { amountToCents, formatMoney, calculateRoomTotals } = new Function(centsSource + moneySource + pricingSource + '; return { amountToCents, formatMoney, calculateRoomTotals };')();
const minimumSource = view.slice(view.indexOf('function getMinimumCheckoutDate('), view.indexOf('function amountToCents('));
const getMinimumCheckoutDate = new Function(minimumSource + '; return getMinimumCheckoutDate;')();

test('form controls restore their own state even when Select2 changes the control list', () => {
    const source = view.slice(view.indexOf('function disableBookingInputs('), view.indexOf('function getTableColumns('));
    const original = [{ disabled: false }, { disabled: true }];
    let controls = original.slice();
    const jquery = element => ({ prop(name, value) { if (value === undefined) return element[name]; element[name] = value; } });
    jquery.each = (items, callback) => items.forEach((item, index) => callback(index, item));
    const form = { find: () => ({ each: callback => controls.forEach((element, index) => callback.call(element, index)) }) };
    const helpers = new Function('$', '$form', source + '; return { disableBookingInputs, restoreBookingInputs };')(jquery, form);
    const states = helpers.disableBookingInputs();
    const added = { disabled: true };
    controls = [added, original[1]];
    helpers.restoreBookingInputs(states);
    assert.equal(original[0].disabled, false);
    assert.equal(original[1].disabled, true);
    assert.equal(added.disabled, true);
});

test('page loader waits for every initial table before revealing the page', () => {
    const layout = readFileSync(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
    const source = layout.slice(layout.indexOf('const pageLoader ='), layout.indexOf('function openModal('));
    const pending = new Set(['first', 'second']);
    const jquery = element => typeof element === 'string' && element.startsWith('#page-content ')
        ? { length: pending.size }
        : { removeAttr: () => pending.delete(element) };
    const loader = new Function('$', source + '; return pageLoader;')(jquery);
    let finished = 0;
    loader.finish = () => { finished++; };
    loader.ready('first');
    assert.equal(finished, 0);
    loader.ready('second');
    assert.equal(finished, 1);
});

test('checkout starts the next calendar day, including month and year boundaries', () => {
    assert.equal(getMinimumCheckoutDate('2026-10-20'), '2026-10-21');
    assert.equal(getMinimumCheckoutDate('2026-10-31'), '2026-11-01');
    assert.equal(getMinimumCheckoutDate('2026-12-31'), '2027-01-01');
    assert.equal(getMinimumCheckoutDate('2028-02-28'), '2028-02-29');
    assert.equal(getMinimumCheckoutDate('2028-02-29'), '2028-03-01');
    assert.equal(getMinimumCheckoutDate(''), '');
});

test('amounts accept integer and decimal input without losing cents', () => {
    assert.equal(amountToCents('5000'), 500000n);
    assert.equal(amountToCents('4000.10'), 400010n);
    assert.equal(amountToCents('0.25'), 25n);
    assert.equal(amountToCents('5000.'), 500000n);
    assert.equal(amountToCents(''), 0n);
    assert.equal(amountToCents('invalid'), 0n);
});

test('ticket columns match pending, feedbacked and sold views', () => {
    const source = view.slice(view.indexOf('function getTableColumns('), view.indexOf('function initializeTicketTable('));
    const columnsFor = status => new Function('status', source + '; return getTableColumns();')(status).map(column => column.data);
    assert.deepEqual(columnsFor('pending'), ['id', 'customer_name', 'customer_mobile', 'whatsapp', 'journey_date', 'action']);
    assert.deepEqual(columnsFor('feedbacked'), ['id', 'customer_name', 'customer_mobile', 'whatsapp', 'remark_text', 'feedback_by', 'action']);
    assert.deepEqual(columnsFor('sold'), ['id', 'customer_name', 'customer_mobile', 'whatsapp', 'hotels', 'stay_dates', 'payment_details', 'sold_by', 'action']);
});

test('booking form data preserves room rates, payment amount and manual fee', () => {
    const source = view.slice(view.indexOf('function buildBookingFormData('), view.indexOf('function bindAttachmentEvents('));
    const jquery = { each: (items, callback) => Object.entries(items).forEach(([key, value]) => callback(key, value)) };
    const paymentRows = { children: () => ({ eq: () => ({ find: () => ({ prop: () => [] }) }) }) };
    const buildFormData = new Function('$', '$payments', source + '; return buildBookingFormData;')(jquery, paymentRows);
    const data = buildFormData({ rooms: [{ customer_price_per_night: '5000.15', partner_price_per_night: '4000.10' }], payments: [{ amount: '1000.25', other_fee: '18.50' }], hotel_advances: [{ bookme_hotel_id: 523, amount: '1000.25' }], remark: 'Advance' });
    assert.equal(data.get('hotel_advances[0][bookme_hotel_id]'), '523');
    assert.equal(data.get('hotel_advances[0][amount]'), '1000.25');
    assert.equal(data.get('rooms[0][customer_price_per_night]'), '5000.15');
    assert.equal(data.get('rooms[0][partner_price_per_night]'), '4000.10');
    assert.equal(data.get('payments[0][amount]'), '1000.25');
    assert.equal(data.get('payments[0][other_fee]'), '18.50');
    assert.equal(data.get('remark'), 'Advance');
});

test('room preview calculates customer and partner totals from nightly rates', () => {
    const result = calculateRoomTotals({
        check_in_date: '2026-10-20', check_out_date: '2026-10-23',
        customer_price_per_night: '5000.15', partner_price_per_night: '4000.10',
    });
    assert.equal(result.nights, 3);
    assert.equal(formatMoney(result.sell), '15000.45');
    assert.equal(formatMoney(result.partner), '12000.30');
    assert.equal(formatMoney(result.sell - result.partner), '3000.15');
    assert.equal(formatMoney(result.sell - amountToCents('2000.25')), '13000.20');
});

test('missing dates produce zero totals', () => {
    const result = calculateRoomTotals({ check_in_date: '', check_out_date: '', customer_price_per_night: '5000', partner_price_per_night: '4000' });
    assert.equal(result.nights, 0);
    assert.equal(formatMoney(result.sell), '0.00');
    assert.equal(formatMoney(result.partner), '0.00');
});

test('reschedule script keeps valid string literals after Blade formatting', () => {
    const script = readFileSync(new URL('../../resources/views/ship_tickets/partials/reschedule-script.blade.php', import.meta.url), 'utf8');
    const source = script.replace(/\{\{--[\s\S]*?--\}\}/g, '').replace(/@json\(url\('[^']+'\)\)/g, '"/test"');
    assert.doesNotThrow(() => new Function(source));
});
