const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const multer = require('multer');

// Ensure upload directories exist
const uploadDir = path.join(__dirname, '..', '..', 'uploads', 'rooms');
if (!fs.existsSync(uploadDir)) {
  fs.mkdirSync(uploadDir, { recursive: true });
}

const receiptsDir = path.join(__dirname, '..', '..', 'uploads', 'receipts');
if (!fs.existsSync(receiptsDir)) {
  fs.mkdirSync(receiptsDir, { recursive: true });
}

// Storage Configuration for Rooms
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, uploadDir);
  },
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    const uniqueId = `room-${Date.now()}-${crypto.randomBytes(4).toString('hex')}${ext}`;
    cb(null, uniqueId);
  }
});

// Storage Configuration for Payment Receipts / Screenshots
const receiptStorage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, receiptsDir);
  },
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname).toLowerCase();
    const uniqueId = `receipt-${Date.now()}-${crypto.randomBytes(4).toString('hex')}${ext}`;
    cb(null, uniqueId);
  }
});

// File validation
const fileFilter = (req, file, cb) => {
  const allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/jpg'];
  if (allowedMimeTypes.includes(file.mimetype.toLowerCase())) {
    cb(null, true);
  } else {
    cb(new Error('Invalid file format. Only JPEG, PNG, and WebP images are permitted.'), false);
  }
};

const upload = multer({
  storage,
  fileFilter,
  limits: {
    fileSize: 8 * 1024 * 1024 // 8MB limit
  }
});

const uploadReceipt = multer({
  storage: receiptStorage,
  fileFilter,
  limits: {
    fileSize: 10 * 1024 * 1024 // 10MB limit for receipts/screenshots
  }
});

// Helper to remove obsolete files
function deleteImageFile(relativeUrl) {
  if (!relativeUrl || !relativeUrl.startsWith('/uploads/rooms/')) return;
  const fileName = path.basename(relativeUrl);
  const filePath = path.join(uploadDir, fileName);
  if (fs.existsSync(filePath)) {
    try {
      fs.unlinkSync(filePath);
      console.log(`[UploadService] Deleted old image file: ${fileName}`);
    } catch (err) {
      console.warn(`[UploadService] Could not delete file ${fileName}:`, err.message);
    }
  }
}

module.exports = {
  upload,
  uploadReceipt,
  uploadDir,
  receiptsDir,
  deleteImageFile
};
