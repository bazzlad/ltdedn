import QRCode from 'qrcode';
import { computed, ref, watch, type Ref } from 'vue';

export interface QRCodeOptions {
    width?: number;
    margin?: number;
    errorCorrectionLevel?: 'L' | 'M' | 'Q' | 'H';
}

export interface UseQRCodeReturn {
    qrDataUrl: Ref<string>;
    isGenerating: Ref<boolean>;
    error: Ref<string | null>;
    generateQR: () => Promise<void>;
}

export function useQRCode(qrValue: Ref<string> | (() => string), options: QRCodeOptions = {}): UseQRCodeReturn {
    const qrDataUrl = ref<string>('');
    const isGenerating = ref<boolean>(false);
    const error = ref<string | null>(null);

    const defaultOptions: Required<QRCodeOptions> = {
        width: 1024,
        margin: 1,
        errorCorrectionLevel: 'M',
        ...options,
    };

    const computedQrValue = computed(() => {
        if (typeof qrValue === 'function') {
            return qrValue();
        }
        return qrValue.value;
    });

    const generateQR = async (): Promise<void> => {
        const value = computedQrValue.value;

        if (!value || value.trim() === '') {
            qrDataUrl.value = '';
            error.value = null;
            return;
        }

        try {
            isGenerating.value = true;
            error.value = null;

            const dataUrl = await QRCode.toDataURL(value, defaultOptions);
            qrDataUrl.value = dataUrl;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to generate QR code';
            qrDataUrl.value = '';
        } finally {
            isGenerating.value = false;
        }
    };

    watch(computedQrValue, generateQR, { immediate: true });

    return {
        qrDataUrl,
        isGenerating,
        error,
        generateQR,
    };
}

export function getQRCodeUrl(qrCode: string, baseUrl?: string): string {
    const base = baseUrl || window.location.origin;
    return `${base}/qr/${qrCode}`;
}

export function downloadQRCode(dataUrl: string, filename: string = 'qrcode.png'): void {
    if (!dataUrl) {
        throw new Error('No QR code data URL provided');
    }

    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

export async function generateAndDownloadQR(qrCode: string, filename: string, options: QRCodeOptions = {}): Promise<void> {
    const qrUrl = getQRCodeUrl(qrCode);

    const defaultOptions: Required<QRCodeOptions> = {
        width: 1024,
        margin: 1,
        errorCorrectionLevel: 'M',
        ...options,
    };

    try {
        const dataUrl = await QRCode.toDataURL(qrUrl, defaultOptions);
        downloadQRCode(dataUrl, filename);
    } catch (error) {
        throw new Error(`Failed to generate and download QR code: ${error instanceof Error ? error.message : 'Unknown error'}`);
    }
}
