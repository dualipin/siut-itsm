/**
 * Generador y Descargador de Código QR Institucional SIUT-ITSM
 * Ubicación: public/assets/js/components/qr-share.js
 */

const composition = {
  width: 800,
  height: 1000,
  qrSize: 600,
  qrY: 140,
  footerHeight: 120,
};

const brandLogoUrl = '/assets/img/logo.webp';
const brandFooterText = 'OST SIUT ITSM';

function ensureQrStylingLoaded() {
  if (window.QRCodeStyling) {
    return Promise.resolve();
  }
  return new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.js';
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error('No se pudo cargar la librería qr-code-styling'));
    document.head.appendChild(script);
  });
}

function getCssVar(name, fallback) {
  if (typeof window === 'undefined') return fallback;
  const val = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return val || fallback;
}

function loadImageFromBlob(blob) {
  return new Promise((resolve, reject) => {
    const img = new Image();
    const url = URL.createObjectURL(blob);
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = (err) => {
      URL.revokeObjectURL(url);
      reject(err);
    };
    img.src = url;
  });
}

async function buildQrCanvas(url) {
  await ensureQrStylingLoaded();
  const primaryColor = getCssVar("--siut-color-primary", getCssVar("--bs-primary", "#611232"));
  const bodyBg = getCssVar("--bs-body-bg", "#ffffff");
  const footerTextColor = getCssVar("--bs-primary-text", "#ffffff");
  const borderColor = getCssVar("--bs-border-color", "#dee2e6");
  const outputCanvas = document.createElement("canvas");
  outputCanvas.width = composition.width;
  outputCanvas.height = composition.height;
  const context = outputCanvas.getContext("2d");
  context.fillStyle = bodyBg;
  context.fillRect(0, 0, composition.width, composition.height);
  
  // Configuración del generador QR con logo y estilos personalizados
  const qrGenerator = new window.QRCodeStyling({
    width: composition.qrSize,
    height: composition.qrSize,
    type: "canvas",
    data: url,
    image: new URL(brandLogoUrl, window.location.origin).toString(),
    qrOptions: {
      errorCorrectionLevel: "H", // Nivel alto para soportar el logo en el centro
    },
    imageOptions: {
      crossOrigin: "anonymous",
      margin: 6,
      imageSize: 0.24,
    },
    dotsOptions: {
      color: primaryColor,
      type: "rounded",
    },
    cornersSquareOptions: {
      color: primaryColor,
      type: "extra-rounded",
    },
    cornersDotOptions: {
      color: primaryColor,
      type: "dot",
    },
    backgroundOptions: {
      color: "#ffffff",
    },
  });
  const qrBlob = await qrGenerator.getRawData("png");
  const qrImage = await loadImageFromBlob(qrBlob);
  // Dibujar QR centrado con marco
  const qrX = (composition.width - composition.qrSize) / 2;
  context.fillStyle = "#ffffff";
  context.fillRect(qrX - 12, composition.qrY - 12, composition.qrSize + 24, composition.qrSize + 24);
  context.strokeStyle = borderColor;
  context.lineWidth = 2;
  context.strokeRect(qrX - 12, composition.qrY - 12, composition.qrSize + 24, composition.qrSize + 24);
  context.drawImage(qrImage, qrX, composition.qrY, composition.qrSize, composition.qrSize);
  // Pie de página institucional
  const footerY = composition.height - composition.footerHeight;
  context.fillStyle = primaryColor;
  context.fillRect(0, footerY, composition.width, composition.footerHeight);
  context.fillStyle = footerTextColor;
  context.font = "700 46px Spline Sans, Inter, sans-serif";
  context.textAlign = "center";
  context.textBaseline = "middle";
  context.fillText(brandFooterText, composition.width / 2, footerY + composition.footerHeight / 2);
  return outputCanvas;
}

function downloadQrCanvas(canvas, filename = 'qr-sindicato.png') {
  const link = document.createElement('a');
  link.download = filename.endsWith('.png') ? filename : `${filename}.png`;
  link.href = canvas.toDataURL('image/png');
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Exponer globalmente
window.QrShare = {
  buildQrCanvas,
  downloadQrCanvas,
  ensureQrStylingLoaded,
  composition,
};
