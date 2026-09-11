const { ipcMain } = require("electron");
const bcrypt = require("bcrypt");
const path = require("path");
const os = require("os");
const fs = require("fs");
const XLSX = require("xlsx");
const { dialog } = require("electron");

// Bulk data import/export across the whole per-clinic MySQL schema — still
// reads/writes `db` directly, unlike authHandlers.js/employeesHandlers.js/
// clinicUsersHandlers.js, which now talk to the centralized backend. This
// hasn't been migrated yet (it spans far more tables than just Users/
// Employees); only reachable for installs that still have a local
// `dbConfig` set.
module.exports = function setupLegacyUserHandlers(db, store) {
// Export Data
ipcMain.handle("export-data", async (event, options) => {
  try {
    const user = store.get("user");
    if (!user || user.role !== 'admin') {
      return {
        success: false,
        message: "Admin privileges required"
      };
    }

    const exportData = {};

    const safeExport = async (tableName, exportKey) => {
      try {
        const [data] = await db.query(`SELECT * FROM \`${tableName}\``);
        exportData[exportKey] = data;
        return true;
      } catch (error) {
        return false;
      }
    };

    if (options.includeClients) await safeExport('Clients', 'clients');
    if (options.includePets) await safeExport('Pets', 'pets');
    if (options.includeAppointments) await safeExport('Appointments', 'appointments');
    if (options.includeEmployees) await safeExport('Employees', 'employees');
    if (options.includeServices) await safeExport('Services', 'services');
    if (options.includeProducts) await safeExport('Products', 'products');
    if (options.includeUsers) await safeExport('Users', 'users');
    if (options.includeCoupons) await safeExport('Coupons', 'coupons');
    if (options.includeBilling) await safeExport('Billing', 'billing');
    if (options.includeBillingItems) await safeExport('BillingItems', 'billingItems');
    if (options.includeAppointmentServices) await safeExport('AppointmentServices', 'appointmentServices');

    const exportDir = path.join(os.homedir(), 'Desktop', 'PetVet-Backup');
    if (!fs.existsSync(exportDir)) {
      fs.mkdirSync(exportDir, { recursive: true });
    }

    // Create Excel workbook
    const workbook = XLSX.utils.book_new();

    // Add each table as a separate sheet
    Object.keys(exportData).forEach((tableName) => {
      if (exportData[tableName].length > 0) {
        const worksheet = XLSX.utils.json_to_sheet(exportData[tableName]);
        XLSX.utils.book_append_sheet(workbook, worksheet, tableName);
      }
    });

    const filePath = path.join(exportDir, `data-export-${Date.now()}.xlsx`);
    XLSX.writeFile(workbook, filePath);

    return {
      success: true,
      message: "Data exported successfully to Excel",
      filePath: filePath,
      exportedTables: Object.keys(exportData)
    };
  } catch (error) {
    console.error("Error exporting data:", error);
    return {
      success: false,
      message: error.message
    };
  }
});

// Import Data - Enhanced version with full schema support
ipcMain.handle("import-data", async (event, filePath) => {
  try {
    const user = store.get("user");
    if (!user || user.role !== 'admin') {
      return {
        success: false,
        message: "Admin privileges required"
      };
    }

    const fileExtension = path.extname(filePath).toLowerCase();
    let importData = {};

    console.log("Importing file:", filePath);

    if (fileExtension === '.xlsx' || fileExtension === '.xls') {
      const workbook = XLSX.readFile(filePath);
      console.log("Excel sheets:", workbook.SheetNames);

      workbook.SheetNames.forEach(sheetName => {
        const worksheet = workbook.Sheets[sheetName];
        const data = XLSX.utils.sheet_to_json(worksheet);

        if (data.length > 0) {
          console.log("Columns in sheet:", Object.keys(data[0]));
        }

        importData[sheetName] = data;
      });
    } else if (fileExtension === '.json') {
      const fileContent = fs.readFileSync(filePath, 'utf8');
      importData = JSON.parse(fileContent);
    } else {
      return {
        success: false,
        message: "Unsupported file format"
      };
    }

    const connection = await db.getConnection();
    await connection.beginTransaction();

    try {
      let importedCount = 0;
      const importResults = {};

      // Import order: Clients -> Users -> Employees -> Services -> Products -> Coupons -> Pets -> Appointments -> Billing -> BillingItems -> AppointmentServices
      const importOrder = [
        { table: 'Clients', key: 'clients', handler: importClients },
        { table: 'Users', key: 'users', handler: importUsers },
        { table: 'Employees', key: 'employees', handler: importEmployees },
        { table: 'Services', key: 'services', handler: importServices },
        { table: 'Products', key: 'products', handler: importProducts },
        { table: 'Coupons', key: 'coupons', handler: importCoupons },
        { table: 'Pets', key: 'pets', handler: importPets },
        { table: 'Appointments', key: 'appointments', handler: importAppointments },
        { table: 'Billing', key: 'billing', handler: importBilling },
        { table: 'BillingItems', key: 'billingItems', handler: importBillingItems },
        { table: 'AppointmentServices', key: 'appointmentServices', handler: importAppointmentServices }
      ];

      // Helper function to find data in any sheet
      const findDataByTable = (tableName) => {
        for (const [sheetName, sheetData] of Object.entries(importData)) {
          if (sheetName.toLowerCase().includes(tableName.toLowerCase()) ||
              (sheetData.length > 0 && Object.keys(sheetData[0]).some(key =>
                key.toLowerCase().includes(tableName.toLowerCase().slice(0, -1))))) {
            return sheetData;
          }
        }
        return [];
      };

      // Import handlers for each table
      async function importClients(data) {
        const clients = data.length > 0 ? data : findDataByTable('clients');
        let count = 0;
        for (const row of clients) {
          const client_name = row.client_name || row['Client Name'] || row.clientName || '';
          const contact_number = row.contact_number || row['Contact Number'] || row.contact || row.phone || '';
          const address = row.address || row['Address'] || null;

          if (client_name && contact_number) {
            const [existing] = await connection.query(
              "SELECT client_id FROM Clients WHERE contact_number = ?",
              [contact_number]
            );

            if (existing.length === 0) {
              await connection.query(
                "INSERT INTO Clients (client_name, contact_number, address) VALUES (?, ?, ?)",
                [client_name, contact_number, address]
              );
              count++;
            }
          }
        }
        return count;
      }

      async function importUsers(data) {
        const users = data.length > 0 ? data : findDataByTable('users');
        let count = 0;
        for (const row of users) {
          const name = row.name || '';
          const username = row.username || '';
          const password = row.password || 'defaultpassword';
          const organization_name = row.organization_name || row.organizationName || '';
          const role = row.role || 'user';
          const phone_number = row.phone_number || row.phoneNumber || null;

          if (name && username && organization_name) {
            const [existing] = await connection.query(
              "SELECT id FROM Users WHERE username = ?",
              [username]
            );

            if (existing.length === 0) {
              const hashedPassword = await bcrypt.hash(password, 10);
              await connection.query(
                "INSERT INTO Users (name, username, password, organization_name, role, phone_number) VALUES (?, ?, ?, ?, ?, ?)",
                [name, username, hashedPassword, organization_name, role, phone_number]
              );
              count++;
            }
          }
        }
        return count;
      }

      async function importEmployees(data) {
        const employees = data.length > 0 ? data : findDataByTable('employees');
        let count = 0;
        for (const row of employees) {
          const name = row.name || '';
          const position = row.position || '';
          const salary = parseFloat(row.salary) || 0;
          const contact = row.contact || row.contactNumber || '';
          const joined_on = row.joined_on || row.joinedOn || new Date().toISOString().split('T')[0];

          if (name && position && contact) {
            const [existing] = await connection.query(
              "SELECT id FROM Employees WHERE contact = ?",
              [contact]
            );

            if (existing.length === 0) {
              await connection.query(
                "INSERT INTO Employees (name, position, salary, contact, joined_on) VALUES (?, ?, ?, ?, ?)",
                [name, position, salary, contact, joined_on]
              );
              count++;
            }
          }
        }
        return count;
      }

      async function importServices(data) {
        const services = data.length > 0 ? data : findDataByTable('services');
        let count = 0;
        for (const row of services) {
          const name = row.name || '';
          const category = row.category || '';
          const base_rate = parseFloat(row.base_rate) || 0;

          if (name && category) {
            const [existing] = await connection.query(
              "SELECT service_id FROM Services WHERE name = ? AND category = ?",
              [name, category]
            );

            if (existing.length === 0) {
              await connection.query(
                "INSERT INTO Services (name, category, base_rate) VALUES (?, ?, ?)",
                [name, category, base_rate]
              );
              count++;
            }
          }
        }
        return count;
      }

      async function importProducts(data) {
        const products = data.length > 0 ? data : findDataByTable('products');
        let count = 0;
        for (const row of products) {
          const barcode_number = row.barcode_number || row.barcodeNumber || '';
          const name = row.name || '';
          const price = parseFloat(row.price) || 0;
          const quantity = parseInt(row.quantity) || 0;

          if (barcode_number && name) {
            const [existing] = await connection.query(
              "SELECT id FROM Products WHERE barcode_number = ?",
              [barcode_number]
            );

            if (existing.length === 0) {
              await connection.query(
                "INSERT INTO Products (barcode_number, name, price, quantity) VALUES (?, ?, ?, ?)",
                [barcode_number, name, price, quantity]
              );
              count++;
            }
          }
        }
        return count;
      }

      async function importCoupons(data) {
        const coupons = data.length > 0 ? data : findDataByTable('coupons');
        let count = 0;
        for (const row of coupons) {
          const code = row.code || '';
          const discount_type = row.discount_type || row.discountType || 'PERCENT';
          const discount_value = parseFloat(row.discount_value || row.discountValue) || 0;
          const start_date = row.start_date || row.startDate || null;
          const expiry_date = row.expiry_date || row.expiryDate || null;
          const usage_limit = parseInt(row.usage_limit || row.usageLimit) || 1;
          const times_used = parseInt(row.times_used || row.timesUsed) || 0;
          const is_active = parseInt(row.is_active || row.isActive) || 1;

          if (code) {
            const [existing] = await connection.query(
              "SELECT coupon_id FROM Coupons WHERE code = ?",
              [code]
            );

            if (existing.length === 0) {
              await connection.query(
                "INSERT INTO Coupons (code, discount_type, discount_value, start_date, expiry_date, usage_limit, times_used, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [code, discount_type, discount_value, start_date, expiry_date, usage_limit, times_used, is_active]
              );
              count++;
            }
          }
        }
        return count;
      }

      async function importPets(data) {
        const pets = data.length > 0 ? data : findDataByTable('pets');
        let count = 0;
        for (const row of pets) {
          const client_id = parseInt(row.client_id || row.clientId) || 0;
          const pet_name = row.pet_name || row.petName || '';
          const sex = row.sex || '';
          const species = row.species || '';
          const breed = row.breed || null;
          const color = row.color || null;
          const date_of_birth = row.date_of_birth || row.dateOfBirth || null;
          const age = row.age || null;

          if (client_id && pet_name && sex && species) {
            // Verify client exists
            const [clientExists] = await connection.query(
              "SELECT client_id FROM Clients WHERE client_id = ?",
              [client_id]
            );

            if (clientExists.length > 0) {
              const [existing] = await connection.query(
                "SELECT pet_id FROM Pets WHERE client_id = ? AND pet_name = ? AND species = ?",
                [client_id, pet_name, species]
              );

              if (existing.length === 0) {
                await connection.query(
                  "INSERT INTO Pets (client_id, pet_name, sex, species, breed, color, date_of_birth, age) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  [client_id, pet_name, sex, species, breed, color, date_of_birth, age]
                );
                count++;
              }
            }
          }
        }
        return count;
      }

      // Simplified handlers for other tables (can be expanded as needed)
      async function importAppointments(data) { return 0; } // Complex relationships
      async function importBilling(data) { return 0; } // Complex relationships
      async function importBillingItems(data) { return 0; } // Complex relationships
      async function importAppointmentServices(data) { return 0; } // Complex relationships

      // Process imports in order
      for (const { table, key, handler } of importOrder) {
        const data = importData[key] || [];
        const count = await handler(data);
        importResults[table] = count;
        importedCount += count;
        console.log(`Imported ${count} records to ${table}`);
      }

      await connection.commit();
      connection.release();

      console.log("Total records imported:", importedCount);
      console.log("Import results:", importResults);

      return {
        success: true,
        message: `Data imported successfully. ${importedCount} records added.`,
        details: importResults
      };
    } catch (error) {
      await connection.rollback();
      connection.release();
      console.error("Error during import transaction:", error);
      throw error;
    }
  } catch (error) {
    console.error("Error importing data:", error);
    return {
      success: false,
      message: error.message
    };
  }
});

ipcMain.handle("open-file-dialog", async (event) => {
  try {
    const result = await dialog.showOpenDialog({
      properties: ['openFile'],
      filters: [
        { name: 'Data Files', extensions: ['json', 'xlsx', 'xls'] },
        { name: 'JSON', extensions: ['json'] },
        { name: 'Excel', extensions: ['xlsx', 'xls'] }
      ]
    });

    if (!result.canceled && result.filePaths.length > 0) {
      return {
        success: true,
        filePath: result.filePaths[0],
        fileName: path.basename(result.filePaths[0])
      };
    }

    return { success: false, message: "No file selected" };
  } catch (error) {
    console.error("Error opening file dialog:", error);
    return { success: false, message: error.message };
  }
});

// Add this debug function to main.js
ipcMain.handle("debug-excel", async (event, filePath) => {
  try {
    const workbook = XLSX.readFile(filePath);
    const result = {};

    workbook.SheetNames.forEach(sheetName => {
      const worksheet = workbook.Sheets[sheetName];
      const data = XLSX.utils.sheet_to_json(worksheet);
      result[sheetName] = {
        rowCount: data.length,
        columns: data.length > 0 ? Object.keys(data[0]) : [],
        firstRow: data.length > 0 ? data[0] : null
      };
    });

    return {
      success: true,
      data: result
    };
  } catch (error) {
    return {
      success: false,
      message: error.message
    };
  }
});
};
