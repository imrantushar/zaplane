"use strict"

const codepoints = require('./assets/icon-src/codepoints.json');

module.exports = {
    inputDir: './assets/icon-src/svgs', // (required)
    outputDir: './assets/library/icons/fonts', // (required)
    fontTypes: ['eot', 'ttf', 'svg', 'woff', 'woff2'],
    assetTypes: ['css', 'html', 'json'],
    codepoints,
    formatOptions: {
        svg: {
            centerHorizontally: true,
            centerVertically: true
        },
        json: {
            indent: 2,
        },
    },
    templates: {
        html: './assets/icon-src/html.hbs',
        css: './assets/icon-src/css.hbs',
        // scss: './assets/icon-src/scss.hbs',
        // sass: './assets/icon-src/json.hbs',
    },
    fontsUrl: 'fonts',
    pathOptions: {
        css: './assets/library/icons/zaplane-icons.css',
        json: './assets/icon-src/codepoints.json',
        // scss: './assets/library/icons/zaplane-icons.scss',
        // sass: './assets/library/icons/zaplane-icons.json',
        // ts: './assets/icon-src/types.d.ts',
    },
    normalize: true,
    name: 'zaplane-icons',
    selector: '.zaplane-icon',
    prefix: 'zaplane-icon-'
};
