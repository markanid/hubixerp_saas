<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Settings\app\Models\Restore;

class ProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function dashboard(){
        $data['productsCount'] = DB::table('product')->count();
        $data['stockValue'] = (float) DB::table('stock')
            ->join('product', 'stock.stock_product_id', '=', 'product.product_code')
            ->where('stock.stock_qty', '>', 0)
            ->value(DB::raw('COALESCE(SUM(COALESCE(product.pprice, 0) * (stock.stock_qty / NULLIF(product.uqty, 0))), 0)'));
        $data['customersCount'] = DB::table('customer')->count();
        $data['purchaseCount'] = DB::table('purchase')->count();
        $data['saleCount'] = DB::table('sales')->count();
        $data['currencySymbol'] = DB::table('company')->value('currency_symbol') ?: '₹';
        $data['title'] = 'Dashboard';
        return view('settings::dashboard', $data);
    }

    public function logout(){
        Auth::logout();
        return redirect()->route('auth.login')->with('success', 'You have been successfully Logged out');
    }

    public function dbBackup(){
        try {
            // Database credentials from config
            $user = Config::get('database.connections.mysql.username');
            $pass = Config::get('database.connections.mysql.password');
            $host = Config::get('database.connections.mysql.host');
            $name = Config::get('database.connections.mysql.database');
            $backup_name = "{$name}_backup_" . date('Y-m-d_H-i-s') . ".sql";
            $tables = "*";

            // Attempt to create a database connection
            $mysqli = new \mysqli($host, $user, $pass, $name);
            if ($mysqli->connect_error) {
                throw new \Exception("Connection failed: " . $mysqli->connect_error);
            }

            $mysqli->select_db($name);
            $mysqli->query("SET NAMES 'utf8'");

            // Get tables from the database
            $target_tables = [];
            $queryTables = $mysqli->query('SHOW TABLES');
            while ($row = $queryTables->fetch_row()) {
                $target_tables[] = $row[0];
            }

            // If specific tables are provided, filter accordingly
            if ($tables != "*") {
                $target_tables = array_intersect($target_tables, explode(',', $tables));
            }

            $content = '';
            foreach ($target_tables as $table) {
                // Create table schema
                $res = $mysqli->query("SHOW CREATE TABLE {$table}");
                $TableMLine = $res->fetch_row();
                $content .= "\n\n{$TableMLine[1]};\n\n";

                // Insert rows
                $result = $mysqli->query("SELECT * FROM {$table}");
                $fields_amount = $result->field_count;
                $rows_num = $mysqli->affected_rows;
                $st_counter = 0;

                while ($row = $result->fetch_row()) {
                    if ($st_counter % 100 == 0 || $st_counter == 0) {
                        $content .= "\nINSERT INTO {$table} VALUES";
                    }
                    $content .= "\n(";
                    for ($i = 0; $i < $fields_amount; $i++) {
                        $row[$i] = isset($row[$i]) ? '"' . str_replace("\n", "\\n", addslashes($row[$i])) . '"' : '""';
                        $content .= $row[$i];
                        if ($i < ($fields_amount - 1)) {
                            $content .= ',';
                        }
                    }
                    $content .= ")";
                    if (($st_counter + 1) % 100 == 0 || $st_counter + 1 == $rows_num) {
                        $content .= ";";
                    } else {
                        $content .= ",";
                    }
                    $st_counter++;
                }
                $content .= "\n\n\n";
            }

            // Laravel response for file download
            return response()->streamDownload(function () use ($content) {
                echo $content;
            }, $backup_name, [
                'Content-Type' => 'application/octet-stream',
                'Content-Transfer-Encoding' => 'Binary',
                'Content-Disposition' => "attachment; filename=\"{$backup_name}\""
            ]);
        } catch (\Exception $e) {
            // Handle exceptions and log error
            Log::error("Database backup failed: {$e->getMessage()}");
            return response()->json(['error' => 'Backup failed, please try again later.'], 500);
        }
    }

    public function restore()
	{
	    // $restore = new Restore();
	    // $output  = $restore->list_all();
	    $data = [
            'title'      => "Restore",
            'page_title' =>	"Restore"
            // 'output'     => $output
             
        ];
        return view('settings::restore_edit', $data);
	}

    public function restoreBackup(Request $request)
    {
        try {

            if ($request->hasFile('backup_file')) {
                $backupFile = $request->file('backup_file');
                $filename = $backupFile->getClientOriginalName();
                $extension = strtolower($backupFile->getClientOriginalExtension());
                $mimeType = $backupFile->getClientMimeType();

                Log::info("Backup file uploaded: {$filename}, extension: {$extension}, MIME type: {$mimeType}");

                // Check if file extension is .sql
                if ($extension !== 'sql') {
                    return redirect()->back()->with('Error', 'Invalid file type. Only .sql files are allowed.');
                }
            } else {
                Log::error('No backup file uploaded.');
                return redirect()->back()->with('Error', 'No file uploaded.');
            }

            // Validate the uploaded file
            // $request->validate([
            //     'backup_file' => 'required|file|regex:/\.sql$/i|max:40960', // Just check the file extension
            // ]);

            // Get the uploaded file and log the details
            $backupFile = $request->file('backup_file');
            Log::info('Uploaded file path: ' . $backupFile->getPathname());

            $filePath = $backupFile->getPathname();
            // Read the SQL file content
            $sqlContent = file_get_contents($filePath);

            // Database credentials from config
            $user = Config::get('database.connections.mysql.username');
            $pass = Config::get('database.connections.mysql.password');
            $host = Config::get('database.connections.mysql.host');
            $name = Config::get('database.connections.mysql.database');

            // Attempt to create a database connection
            $mysqli = new \mysqli($host, $user, $pass, $name);
            if ($mysqli->connect_error) {
                throw new \Exception("Connection failed: " . $mysqli->connect_error);
            }

            // Split the SQL content into individual queries
            $queries = $this->splitSqlQueries($sqlContent);

            // Execute each query
            foreach ($queries as $query) {
                if (trim($query) !== "") {
                    // Check if the query is a CREATE TABLE query
                    if (stripos($query, 'CREATE TABLE') !== false) {
                        // Extract the table name from the CREATE TABLE query
                        preg_match('/CREATE TABLE `?(\w+)`?/i', $query, $matches);
                        if (isset($matches[1])) {
                            $tableName = $matches[1];

                            // Check if the table already exists
                            $checkTableQuery = "SHOW TABLES LIKE '{$tableName}'";
                            $result = $mysqli->query($checkTableQuery);
                            if ($result && $result->num_rows > 0) {
                                // Table exists, skip the CREATE TABLE query
                                Log::info("Table '{$tableName}' already exists, skipping CREATE TABLE.");
                                continue;  // Skip this query
                            }
                        }
                    }

                    // Execute the query if it’s not skipped
                    if (!$mysqli->query($query)) {
                        throw new \Exception("Error executing query: " . $mysqli->error);
                    }
                }
            }

            // Close the database connection
            $mysqli->close();

            // Return a success message
            return redirect()->back()->with('Success', 'Database restored successfully!');
            
        } catch (\Exception $e) {
            // Log the error and show a failure message
            Log::error("Database restore failed: {$e->getMessage()}");
            return redirect()->back()->with('Error', 'Restore failed, please try again later.');
        }
    }

    /**
     * Helper method to split the SQL file content into individual queries
     */
    private function splitSqlQueries($sqlContent)
    {
        // Remove comments and split the SQL content into individual queries
        $sqlContent = preg_replace("/(--[^\n]*\n)|\/\*.*?\*\//s", "", $sqlContent);  // Remove comments
        $queries = explode(";\n", $sqlContent);  // Split by semicolon (end of each SQL query)

        return $queries;
    }
}
