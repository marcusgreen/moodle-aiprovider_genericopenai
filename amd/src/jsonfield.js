// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Check the JSON in a textarea as it is typed, and offer buttons to tidy its layout and to download it.
 *
 * The textarea grows to fit its content. The server checks the value again when the form is saved.
 *
 * @module     aiprovider_genericopenai/jsonfield
 * @copyright  2026 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/** @var {Number} Milliseconds to wait after typing stops before checking the JSON. */
const DELAY = 300;

/** @var {Number} The most rows the textarea grows to. Longer content scrolls. */
const MAX_ROWS = 25;

/**
 * Get the line and column of a character offset in some text.
 *
 * @param {String} text The text.
 * @param {Number} offset The offset.
 * @returns {{line: Number, column: Number}}
 */
const getLineColumn = (text, offset) => {
    const before = text.slice(0, offset).split('\n');
    return {line: before.length, column: before[before.length - 1].length + 1};
};

/**
 * Check some JSON.
 *
 * Browsers word parse errors differently. Chrome gives a character position, which is turned into a line and column.
 *
 * @param {String} text The JSON. Empty text is valid.
 * @returns {Promise<String>} The error message, or an empty string if the JSON is valid.
 */
const check = async(text) => {
    if (text.trim() === '') {
        return '';
    }
    try {
        JSON.parse(text);
        return '';
    } catch (error) {
        let message = error.message;
        const position = message.match(/at position (\d+)/);
        if (position && !/line \d+/.test(message)) {
            const {line, column} = getLineColumn(text, parseInt(position[1], 10));
            message += ' ' + await getString('json:linecolumn', 'aiprovider_genericopenai', {line, column});
        }
        return getString('json:invalid', 'aiprovider_genericopenai', message);
    }
};

/**
 * Save some text as a file in the browser.
 *
 * @param {String} text The file content.
 * @param {String} filename The file name.
 */
const download = (text, filename) => {
    const url = URL.createObjectURL(new Blob([text], {type: 'application/json'}));
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

/**
 * Add JSON checking, and the format and download buttons, to a textarea.
 *
 * @param {String} id The textarea id.
 * @param {String} filename The file name for the download button, or an empty string for no download button.
 */
export const init = async(id, filename = '') => {
    const textarea = document.getElementById(id);
    if (!textarea || textarea.dataset.jsonfield) {
        return;
    }
    textarea.dataset.jsonfield = '1';
    textarea.classList.add('text-monospace', 'aiprovider_genericopenai-jsonfield');
    textarea.spellcheck = false;

    const feedback = document.createElement('div');
    feedback.id = `${id}_jsonerror`;
    feedback.className = 'invalid-feedback';
    feedback.setAttribute('aria-live', 'polite');

    const buttons = document.createElement('div');
    buttons.className = 'mt-2 d-flex gap-2';
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-secondary btn-sm';
    button.textContent = await getString('json:format', 'aiprovider_genericopenai');
    buttons.append(button);

    let downloadbutton = null;
    if (filename) {
        downloadbutton = document.createElement('button');
        downloadbutton.type = 'button';
        downloadbutton.className = 'btn btn-secondary btn-sm';
        downloadbutton.textContent = await getString('json:download', 'aiprovider_genericopenai');
        downloadbutton.addEventListener('click', () => download(textarea.value, filename));
        buttons.append(downloadbutton);
    }

    textarea.after(feedback, buttons);

    // The fewest rows are those set on the textarea, so an empty field stays small. Rows are added while the
    // content, including long lines that wrap, does not fit.
    const minrows = textarea.rows;
    const resize = () => {
        textarea.rows = minrows;
        while (textarea.scrollHeight > textarea.clientHeight && textarea.rows < MAX_ROWS) {
            textarea.rows += 1;
        }
        if (downloadbutton) {
            downloadbutton.disabled = textarea.value.trim() === '';
        }
    };
    const describedby = textarea.getAttribute('aria-describedby');
    textarea.setAttribute('aria-describedby', describedby ? `${describedby} ${feedback.id}` : feedback.id);

    /**
     * Show whether the JSON in the textarea is valid.
     *
     * @returns {Promise<Boolean>} Whether it is valid.
     */
    const validate = async() => {
        const message = await check(textarea.value);
        feedback.textContent = message;
        // Bootstrap only shows the feedback next to an invalid field, so d-block keeps it visible.
        feedback.classList.toggle('d-block', message !== '');
        textarea.classList.toggle('is-invalid', message !== '');
        textarea.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');
        return message === '';
    };

    let timer = null;
    textarea.addEventListener('input', () => {
        resize();
        clearTimeout(timer);
        timer = setTimeout(validate, DELAY);
    });
    // A value set by script, such as a file loaded into the models setting, raises change rather than input.
    textarea.addEventListener('change', () => {
        resize();
        validate();
    });

    button.addEventListener('click', async() => {
        if (textarea.value.trim() === '' || !await validate()) {
            return;
        }
        textarea.value = JSON.stringify(JSON.parse(textarea.value), null, 4);
        // Let the form change checker know the field changed.
        textarea.dispatchEvent(new Event('change', {bubbles: true}));
    });

    resize();
    validate();
};
