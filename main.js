const { app, BrowserWindow, ipcMain } = require('electron');
const url = require('url');
const path = require('path');
const Store = require("electron-store").default;
const { autoUpdater } = require('electron-updater');
require('dotenv').config();
const dotenv = require("dotenv");
const log = require('electron-log');
const { startServer } = require('./server');

log.transports.file.level = 'info';
console.error = log.error; // route existing console.error calls into the
                            // persisted log too, so nothing needs rewriting
                            // everywhere at once

process.on('unhandledRejection', (reason) => {
  log.error('[unhandledRejection]', reason);
});

const setupAuthHandlers = require('./handlers/authHandlers');
const setupEmployeesHandlers = require('./handlers/employeesHandlers');
const setupClinicUsersHandlers = require('./handlers/clinicUsersHandlers');
const setupFormsHandlers = require('./handlers/formsHandlers');
const setupBranchesHandlers = require('./handlers/branchesHandlers');
const setupSubscriptionHandlers = require('./handlers/subscriptionHandlers');
const setupPaymentSubmissionHandlers = require('./handlers/paymentSubmissionHandlers');
const setupClientsHandlers = require('./handlers/clientsHandlers');
const setupPetsHandlers = require('./handlers/petsHandlers');
const setupServicesHandlers = require('./handlers/servicesHandlers');
const setupClientBillingHandlers = require('./handlers/clientBillingHandlers');
const setupProductsHandlers = require('./handlers/productsHandlers');
const setupVendorsHandlers = require('./handlers/vendorsHandlers');
const setupBillingHandlers = require('./handlers/billingHandlers');
const setupSettingsHandlers = require('./handlers/settingsHandlers');
const setupPaymentsHandlers = require('./handlers/paymentsHandlers');
const setupAppointmentProductsHandlers = require('./handlers/appointmentProductsHandlers');
const setupAppointmentsHandlers = require('./handlers/appointmentsHandlers');
const setupReportsHandlers = require('./handlers/reportsHandlers');
const setupRemindersHandlers = require('./handlers/remindersHandlers');
const setupReminderNotifications = require('./handlers/reminderNotifications');
const checkForPostUpdateNotification = require('./handlers/updateNotification');
const setupExpensesHandlers = require('./handlers/expensesHandlers');
const setupRecordHandlers = require('./handlers/recordHandlers');
const setupDataManagementHandlers = require('./handlers/dataManagementHandlers');
const setupBoardingHandlers = require('./handlers/boardingHandlers');
const setupAiProductHandlers = require('./handlers/ai/productHandler');
const setupSoapTextHandlers = require('./handlers/ai/soapTextHandler');

// Single dotenv call, after app/path are available
dotenv.config({
  path: app.isPackaged
    ? path.join(process.resourcesPath, ".env")
    : path.resolve(__dirname, ".env")
});

const store = new Store();

let mainWindow;

// Every handler is API-backed now — Employees/Users/Branches/Clients/Pets/
// Records(EMR)/Reminders/Services/Coupons/Vendors/Products/Billing/
// Appointments(+Payments+Products)/ClientBilling/Settings(+Branding)/
// Expenses/Reports/Data Management(export/import)/Profile have all fully
// moved off the per-clinic local MySQL that used to gate a chunk of this
// list behind a local `dbConfig` — see handlers/_legacy_backup/ for the
// retired local-DB handlers, kept only for reference.
setupAuthHandlers(store);
setupEmployeesHandlers();
setupClinicUsersHandlers();
setupFormsHandlers();
setupBranchesHandlers();
setupSubscriptionHandlers();
setupPaymentSubmissionHandlers();
setupClientsHandlers();
setupPetsHandlers();
setupRecordHandlers();
setupRemindersHandlers();
setupServicesHandlers();
setupProductsHandlers();
setupVendorsHandlers();
setupBillingHandlers(store);
setupAppointmentProductsHandlers();
setupAppointmentsHandlers(store);
setupPaymentsHandlers(store);
setupClientBillingHandlers(store);
setupSettingsHandlers(store);
setupExpensesHandlers(store);
setupReportsHandlers();
setupDataManagementHandlers();
setupBoardingHandlers(store);
setupAiProductHandlers();
setupSoapTextHandlers();

function createMainWindow(showSplash) {
  let splash;

  if (showSplash) {
    splash = new BrowserWindow({
      fullscreen : true,
      transparent: true,
      frame: false,
      alwaysOnTop: true,
      resizable: true,
    });

    splash.loadFile(path.join(__dirname, "splash.html"));
  }

  mainWindow = new BrowserWindow({
    title: 'Vet Management System (Pro)',
    fullscreen: false,
    frame: true,
    autoHideMenuBar: true,
    show: false,
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    }
  });

  const indexPath = path.join(__dirname, 'pet-vet', 'dist', 'index.html');
  mainWindow.loadURL(url.pathToFileURL(indexPath).toString());

  mainWindow.once('ready-to-show', () => {
    if (showSplash) {
      setTimeout(() => {
        splash.close();
        mainWindow.maximize();
        mainWindow.show();
      }, 3000);
    } else {
      mainWindow.maximize();
      mainWindow.show();
    }
  });
}

// Start embedded API server in-process (works in both dev and packaged Electron)
async function startEmbeddedServer() {
  try {
    await startServer();
    return true;
  } catch (err) {
    log.error('[server] failed to start:', err.message);
    return false;
  }
}

app.whenReady().then(async () => {
  await startEmbeddedServer();
  createMainWindow(true);
  setupReminderNotifications(store, () => mainWindow);
  checkForPostUpdateNotification(store, () => mainWindow);
  if (app.isPackaged) {
    autoUpdater.autoDownload = true;
    autoUpdater.checkForUpdates();
  }
});

autoUpdater.on('update-available', (info) => {
  mainWindow?.webContents.send('update-event', { status: 'available', version: info.version });
});
autoUpdater.on('download-progress', (progress) => {
  mainWindow?.webContents.send('update-event', { status: 'downloading', percent: progress.percent });
});
autoUpdater.on('update-downloaded', () => {
  mainWindow?.webContents.send('update-event', { status: 'ready' });
});
autoUpdater.on('error', (err) => {
  console.error('[autoUpdater]', err.message);
});

ipcMain.handle('restart-app-to-update', () => {
  autoUpdater.quitAndInstall();
});

// No child process cleanup needed — server runs in-process
if (typeof window !== 'undefined' && !window.process) {
    window.process = { env: {} };
    window.require = function(module) {
        console.warn(`Bypassed desktop module: ${module}`);
        return { 
            ipcRenderer: { on: () => {}, send: () => {} },
            fs: { readFileSync: () => "", writeFileSync: () => "" }
        };
    };
}

