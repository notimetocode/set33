import html2canvas from 'html2canvas';
import { jsPDF } from 'jspdf';

/**
 * @param {string} value
 * @returns {string}
 */
export function slugifyFilenamePart(value) {
    const slug = String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]+/gu, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 48);

    return slug || 'otchet';
}

/**
 * @param {{
 *   siteName?: string,
 *   periodFrom?: string,
 *   periodTo?: string,
 * }} options
 * @returns {string}
 */
export function buildAiReportPdfFilename(options = {}) {
    const parts = ['ai-otchet', slugifyFilenamePart(options.siteName)];

    if (options.periodFrom) {
        parts.push(String(options.periodFrom));
    }

    if (options.periodTo && options.periodTo !== options.periodFrom) {
        parts.push(String(options.periodTo));
    }

    return `${parts.filter(Boolean).join('_')}.pdf`;
}

/**
 * Temporarily replace canvases with PNG images so html2canvas keeps chart pixels.
 *
 * @param {HTMLElement} root
 * @returns {() => void}
 */
function replaceCanvasesWithImages(root) {
    /** @type {Array<() => void>} */
    const restores = [];

    root.querySelectorAll('canvas').forEach((canvas) => {
        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }

        const parent = canvas.parentNode;

        if (!parent) {
            return;
        }

        const img = document.createElement('img');
        const rect = canvas.getBoundingClientRect();

        img.src = canvas.toDataURL('image/png');
        img.alt = '';
        img.width = Math.max(1, Math.round(rect.width || canvas.width));
        img.height = Math.max(1, Math.round(rect.height || canvas.height));
        img.style.display = 'block';
        img.style.width = `${img.width}px`;
        img.style.height = `${img.height}px`;
        img.style.maxWidth = '100%';

        parent.replaceChild(img, canvas);
        restores.push(() => {
            if (img.parentNode) {
                img.parentNode.replaceChild(canvas, img);
            }
        });
    });

    return () => {
        while (restores.length) {
            restores.pop()?.();
        }
    };
}

/**
 * @param {HTMLElement} element
 * @param {{ title?: string, subtitle?: string }} meta
 * @returns {() => void}
 */
function prependPdfHeader(element, meta) {
    const title = String(meta.title || '').trim();
    const subtitle = String(meta.subtitle || '').trim();

    if (!title && !subtitle) {
        return () => {};
    }

    const header = document.createElement('div');
    header.setAttribute('data-ai-report-pdf-header', '1');
    header.style.cssText = [
        'margin: 0 0 1rem',
        'padding: 0 0 0.85rem',
        'border-bottom: 1px solid rgba(15, 23, 18, 0.12)',
        'font-family: "Instrument Sans", system-ui, sans-serif',
        'color: #12151a',
    ].join(';');

    if (title) {
        const heading = document.createElement('div');
        heading.textContent = title;
        heading.style.cssText = [
            'margin: 0 0 0.35rem',
            'font-size: 1.25rem',
            'font-weight: 700',
            'letter-spacing: -0.02em',
            'line-height: 1.25',
        ].join(';');
        header.appendChild(heading);
    }

    if (subtitle) {
        const line = document.createElement('div');
        line.textContent = subtitle;
        line.style.cssText = [
            'margin: 0',
            'font-size: 0.8125rem',
            'line-height: 1.4',
            'color: #5c6670',
        ].join(';');
        header.appendChild(line);
    }

    element.insertBefore(header, element.firstChild);

    return () => {
        header.remove();
    };
}

/**
 * Capture a rendered AI report DOM node (including Chart.js canvases) into a PDF.
 *
 * @param {{
 *   element: HTMLElement,
 *   filename?: string,
 *   title?: string,
 *   subtitle?: string,
 * }} options
 * @returns {Promise<void>}
 */
export async function downloadAiReportPdf(options) {
    const element = options.element;

    if (!(element instanceof HTMLElement)) {
        throw new Error('Не найден блок отчёта для экспорта');
    }

    const filename = String(options.filename || 'ai-otchet.pdf').replace(/[\\/:*?"<>|]+/g, '-');
    /** @type {Array<() => void>} */
    const restores = [];

    try {
        restores.push(prependPdfHeader(element, {
            title: options.title,
            subtitle: options.subtitle,
        }));
        restores.push(replaceCanvasesWithImages(element));

        await new Promise((resolve) => {
            window.requestAnimationFrame(() => resolve());
        });

        const canvas = await html2canvas(element, {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            scrollX: 0,
            scrollY: 0,
            windowWidth: Math.max(element.scrollWidth, element.clientWidth),
        });

        const imgData = canvas.toDataURL('image/jpeg', 0.92);
        const pdf = new jsPDF({
            orientation: 'p',
            unit: 'mm',
            format: 'a4',
            compress: true,
        });

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 10;
        const contentWidth = pageWidth - margin * 2;
        const contentHeight = (canvas.height * contentWidth) / canvas.width;
        const pageContentHeight = pageHeight - margin * 2;

        let heightLeft = contentHeight;
        let position = margin;

        pdf.addImage(imgData, 'JPEG', margin, position, contentWidth, contentHeight, undefined, 'FAST');
        heightLeft -= pageContentHeight;

        while (heightLeft > 0) {
            position = margin - (contentHeight - heightLeft);
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', margin, position, contentWidth, contentHeight, undefined, 'FAST');
            heightLeft -= pageContentHeight;
        }

        pdf.save(filename);
    } finally {
        while (restores.length) {
            restores.pop()?.();
        }
    }
}
