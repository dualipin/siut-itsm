import forge from 'node-forge';

export interface EfirmaCertPreview {
    rfc: string;
    curp: string;
    signerName: string;
    serial: string;
    validFrom: string;
    validTo: string;
    isExpired: boolean;
    isNotYetValid: boolean;
    isCsd: boolean;
    hasDigitalSignature: boolean;
}

export interface EfirmaSignResult {
    certificateBase64: string;
    originalChain: string;
    sealBase64: string;
    termsHash: string;
    rfc: string;
    curp: string;
    signerName: string;
    serial: string;
    validFrom: string;
    validTo: string;
}

const CURP_REGEX = /[A-Z]{4}\d{6}[HM][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d/;
const RFC_REGEX = /[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}/;

export function formatSatSerial(serialHex: string): string {
    if (!serialHex) return '';
    if (/^([0-9a-fA-F]{2})+$/.test(serialHex)) {
        let ascii = '';
        for (let i = 0; i < serialHex.length; i += 2) {
            ascii += String.fromCharCode(parseInt(serialHex.substring(i, i + 2), 16));
        }
        if (/^\d+$/.test(ascii)) {
            return ascii;
        }
    }
    return serialHex;
}

export function readFileAsBinary(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            try {
                const buffer = reader.result as ArrayBuffer;
                const bytes = new Uint8Array(buffer);
                let binary = '';
                const chunkSize = 8192;
                for (let i = 0; i < bytes.length; i += chunkSize) {
                    binary += String.fromCharCode.apply(
                        null,
                        Array.from(bytes.subarray(i, i + chunkSize))
                    );
                }
                resolve(binary);
            } catch (err) {
                reject(new Error('Error al procesar los bytes del archivo.'));
            }
        };
        reader.onerror = () => reject(new Error('No se pudo leer el archivo seleccionado.'));
        reader.readAsArrayBuffer(file);
    });
}

export function parseCertificate(certBinary: string): {
    cert: forge.pki.Certificate;
    preview: EfirmaCertPreview;
    certificateBase64: string;
} {
    let asn1: forge.asn1.Asn1;
    try {
        asn1 = forge.asn1.fromDer(certBinary);
    } catch {
        throw new Error('El archivo .cer no tiene un formato DER/ASN.1 válido.');
    }

    let cert: forge.pki.Certificate;
    try {
        cert = forge.pki.certificateFromAsn1(asn1);
    } catch {
        throw new Error('No fue posible interpretar el certificado X.509.');
    }

    let curp = '';
    let rfc = '';
    let signerName = '';
    let isCsd = false;

    for (const a of cert.subject.attributes) {
        const val = String(a.value || '');
        const type = String(a.type || '');
        const name = String(a.name || '');

        if (name === 'commonName' || type === '2.5.4.3') {
            signerName = val;
        }
        if (name === 'serialNumber' || type === '2.5.4.5') {
            const match = val.toUpperCase().match(CURP_REGEX);
            if (match) curp = match[0];
        }
        if (name === 'x500UniqueIdentifier' || type === '2.5.4.45') {
            const match = val.toUpperCase().match(RFC_REGEX);
            if (match) rfc = match[0];
        }
        if (name === 'organizationalUnitName' || type === '2.5.4.11') {
            isCsd = true;
        }
    }

    // Fallback search across any attribute value if not matched yet
    if (!curp || !rfc) {
        for (const a of cert.subject.attributes) {
            const val = String(a.value || '').toUpperCase();
            if (!curp) {
                const cMatch = val.match(CURP_REGEX);
                if (cMatch) curp = cMatch[0];
            }
            if (!rfc) {
                const rMatch = val.match(RFC_REGEX);
                if (rMatch) rfc = rMatch[0];
            }
        }
    }

    let hasDigitalSignature = false;
    const keyUsageExt = cert.getExtension('keyUsage') as any;
    if (keyUsageExt) {
        hasDigitalSignature = Boolean(keyUsageExt.digitalSignature);
    } else {
        hasDigitalSignature = true;
    }

    const now = new Date();
    const isExpired = now > cert.validity.notAfter;
    const isNotYetValid = now < cert.validity.notBefore;

    const preview: EfirmaCertPreview = {
        rfc,
        curp,
        signerName,
        serial: formatSatSerial(cert.serialNumber),
        validFrom: cert.validity.notBefore.toISOString().split('T')[0],
        validTo: cert.validity.notAfter.toISOString().split('T')[0],
        isExpired,
        isNotYetValid,
        isCsd,
        hasDigitalSignature,
    };

    const certificateBase64 = forge.util.encode64(certBinary);

    return { cert, preview, certificateBase64 };
}

export function decryptSatPrivateKey(keyBinary: string, password: string): forge.pki.rsa.PrivateKey {
    let asn1: forge.asn1.Asn1;
    try {
        asn1 = forge.asn1.fromDer(keyBinary);
    } catch {
        throw new Error('El archivo .key no tiene un formato DER/ASN.1 válido.');
    }

    // Attempt 1: decryptPrivateKeyInfo directly from ASN.1
    try {
        const privateKeyInfo = forge.pki.decryptPrivateKeyInfo(asn1, password);
        if (privateKeyInfo) {
            return forge.pki.privateKeyFromAsn1(privateKeyInfo) as forge.pki.rsa.PrivateKey;
        }
    } catch {
        // Continue to fallback
    }

    // Attempt 2: via PEM conversion (handles some PBES1/PBES2 variants)
    try {
        const pem = forge.pki.encryptedPrivateKeyToPem(asn1);
        const key = forge.pki.decryptRsaPrivateKey(pem, password);
        if (key) {
            return key as forge.pki.rsa.PrivateKey;
        }
    } catch {
        // Continue to error
    }

    throw new Error('Contraseña incorrecta o el formato de clave privada no pudo ser descifrado.');
}

export function buildOriginalChain(termsHash: string, date: Date = new Date()): string {
    const isoUtc = date.toISOString().replace(/\.\d{3}Z$/, 'Z');
    return `||SIUT-ITSM-PRESTAMO|v1|${termsHash}|${isoUtc}||`;
}

export async function signEfirma(options: {
    cerFile: File;
    keyFile: File;
    password: string;
    termsHash: string;
    expectedCurp?: string | null;
}): Promise<EfirmaSignResult> {
    const { cerFile, keyFile, password, termsHash, expectedCurp } = options;

    if (!cerFile) {
        throw new Error('Debes seleccionar el archivo del certificado digital (.cer).');
    }
    if (!keyFile) {
        throw new Error('Debes seleccionar el archivo de la clave privada (.key).');
    }
    if (!password) {
        throw new Error('Debes ingresar la contraseña de la clave privada.');
    }
    if (!termsHash) {
        throw new Error('No se han definido los términos a firmar.');
    }

    const [certBinary, keyBinary] = await Promise.all([
        readFileAsBinary(cerFile),
        readFileAsBinary(keyFile),
    ]);

    const { cert, preview, certificateBase64 } = parseCertificate(certBinary);

    if (preview.isCsd) {
        throw new Error('El certificado corresponde a un CSD (Sello Digital de facturación). Debes usar tu e.firma de persona física.');
    }
    if (preview.isExpired) {
        throw new Error(`El certificado está vencido desde el ${preview.validTo}. Debes renovarlo en el SAT.`);
    }
    if (preview.isNotYetValid) {
        throw new Error(`El certificado aún no entra en vigencia (${preview.validFrom}).`);
    }
    if (!preview.hasDigitalSignature) {
        throw new Error('El certificado no cuenta con permiso para firma digital.');
    }
    if (!preview.curp || !preview.rfc) {
        throw new Error('El certificado no contiene CURP y RFC de persona física reconocibles.');
    }

    if (expectedCurp && expectedCurp.trim()) {
        const normalizedExpected = expectedCurp.trim().toUpperCase();
        if (preview.curp.toUpperCase() !== normalizedExpected) {
            throw new Error(`El CURP del certificado (${preview.curp}) no coincide con el agremiado registrado (${normalizedExpected}).`);
        }
    }

    const privateKey = decryptSatPrivateKey(keyBinary, password);

    // Verify key modulus matches certificate modulus
    if (cert.publicKey.n.toString() !== privateKey.n.toString()) {
        throw new Error('La clave privada (.key) no corresponde al certificado digital (.cer) seleccionado.');
    }

    const originalChain = buildOriginalChain(termsHash);

    const md = forge.md.sha256.create();
    md.update(originalChain, 'utf8');
    const rawSignature = privateKey.sign(md);
    const sealBase64 = forge.util.encode64(rawSignature);

    return {
        certificateBase64,
        originalChain,
        sealBase64,
        termsHash,
        rfc: preview.rfc,
        curp: preview.curp,
        signerName: preview.signerName,
        serial: preview.serial,
        validFrom: preview.validFrom,
        validTo: preview.validTo,
    };
}

// Alpine component definition
export function efirmaSignerAlpine(config: {
    expectedCurp?: string | null;
    getTermsHash: () => string;
    onSigned?: (result: EfirmaSignResult) => void;
    onReset?: () => void;
}) {
    return {
        cerFile: null as File | null,
        keyFile: null as File | null,
        password: '',
        showPassword: false,
        loading: false,
        errorMessage: null as string | null,
        preview: null as EfirmaCertPreview | null,
        signedResult: null as EfirmaSignResult | null,

        handleCerChange(event: Event) {
            const input = event.target as HTMLInputElement;
            this.cerFile = input.files?.[0] || null;
            this.errorMessage = null;
            this.preview = null;

            if (this.cerFile) {
                readFileAsBinary(this.cerFile)
                    .then((bin) => {
                        try {
                            const { preview } = parseCertificate(bin);
                            this.preview = preview;
                        } catch (err: any) {
                            this.errorMessage = err.message || 'Error al leer el certificado';
                        }
                    })
                    .catch((err) => {
                        this.errorMessage = err.message;
                    });
            }
        },

        handleKeyChange(event: Event) {
            const input = event.target as HTMLInputElement;
            this.keyFile = input.files?.[0] || null;
            this.errorMessage = null;
        },

        async sign() {
            this.errorMessage = null;
            this.loading = true;

            try {
                if (!this.cerFile) throw new Error('Por favor selecciona tu certificado (.cer)');
                if (!this.keyFile) throw new Error('Por favor selecciona tu clave privada (.key)');
                if (!this.password) throw new Error('Por favor ingresa tu contraseña de clave privada');

                const termsHash = config.getTermsHash();
                if (!termsHash) {
                    throw new Error('No se encontraron los términos de la solicitud para firmar.');
                }

                const result = await signEfirma({
                    cerFile: this.cerFile,
                    keyFile: this.keyFile,
                    password: this.password,
                    termsHash,
                    expectedCurp: config.expectedCurp,
                });

                this.signedResult = result;
                // Zero out password and files from component memory
                this.password = '';
                this.keyFile = null;
                this.cerFile = null;

                if (config.onSigned) {
                    config.onSigned(result);
                }
            } catch (err: any) {
                this.errorMessage = err.message || 'Error durante la firma electrónica.';
            } finally {
                this.loading = false;
            }
        },

        reset(clearCallback: boolean = true) {
            this.cerFile = null;
            this.keyFile = null;
            this.password = '';
            this.showPassword = false;
            this.errorMessage = null;
            this.preview = null;
            this.signedResult = null;
            if (clearCallback && config.onReset) {
                config.onReset();
            }
        },
    };
}

// Attach globally for Blade / Alpine access
if (typeof window !== 'undefined') {
    (window as any).efirmaSigner = {
        parseCertificate,
        decryptSatPrivateKey,
        buildOriginalChain,
        signEfirma,
        efirmaSignerAlpine,
    };
}
