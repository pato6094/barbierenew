<?php
session_start();
if (!isset($_SESSION['logged'])) {
    header("Location: login.php");
    exit();
}
include 'connessione.php';

// Handle booking actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'confirm') {
        // Check if 'stato' column exists, if not use a default status system
        $result = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'stato'");
        if ($result->num_rows > 0) {
            $conn->query("UPDATE prenotazioni SET stato = 'Confermata' WHERE id = $id");
        } else {
            // If no stato column, we'll just mark it somehow or create the column
            $conn->query("ALTER TABLE prenotazioni ADD COLUMN stato VARCHAR(50) DEFAULT 'In attesa'");
            $conn->query("UPDATE prenotazioni SET stato = 'Confermata' WHERE id = $id");
        }
    } elseif ($_GET['action'] === 'cancel') {
        $result = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'stato'");
        if ($result->num_rows > 0) {
            $conn->query("UPDATE prenotazioni SET stato = 'Cancellata' WHERE id = $id");
        } else {
            $conn->query("ALTER TABLE prenotazioni ADD COLUMN stato VARCHAR(50) DEFAULT 'In attesa'");
            $conn->query("UPDATE prenotazioni SET stato = 'Cancellata' WHERE id = $id");
        }
    }
    header("Location: admin.php");
    exit();
}

// Handle add service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $nome = $conn->real_escape_string(trim($_POST['nome_servizio']));
    $prezzo = floatval($_POST['prezzo_servizio']);
    if ($nome !== '' && $prezzo > 0) {
        // Check if servizi table exists and has the right columns
        $result = $conn->query("SHOW TABLES LIKE 'servizi'");
        if ($result->num_rows == 0) {
            // Create servizi table if it doesn't exist
            $conn->query("CREATE TABLE servizi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                prezzo DECIMAL(10,2) NOT NULL
            )");
        }
        $conn->query("INSERT INTO servizi (nome, prezzo) VALUES ('$nome', $prezzo)");
        header("Location: admin.php");
        exit();
    } else {
        $error_message = "Inserisci un nome valido e un prezzo maggiore di 0.";
    }
}

// Handle clear bookings
if (isset($_POST['clear_prenotazioni'])) {
    // Check if storico_ricavi table exists
    $result = $conn->query("SHOW TABLES LIKE 'storico_ricavi'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE storico_ricavi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            data DATE NOT NULL UNIQUE,
            ricavo DECIMAL(10,2) NOT NULL DEFAULT 0
        )");
    }

    // Check if we have the necessary columns and tables
    $servizi_exists = $conn->query("SHOW TABLES LIKE 'servizi'")->num_rows > 0;
    $stato_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'stato'")->num_rows > 0;
    
    if ($servizi_exists && $stato_exists) {
        $ricavi_query = $conn->query("
            SELECT p.data_prenotazione, SUM(s.prezzo) as totale
            FROM prenotazioni p 
            JOIN servizi s ON p.servizio = s.nome
            WHERE p.stato = 'Confermata'
            GROUP BY p.data_prenotazione
        ");

        if ($ricavi_query) {
            while ($row = $ricavi_query->fetch_assoc()) {
                $data = $conn->real_escape_string($row['data_prenotazione']);
                $totale = floatval($row['totale']);
                $conn->query("
                    INSERT INTO storico_ricavi (data, ricavo)
                    VALUES ('$data', $totale)
                    ON DUPLICATE KEY UPDATE ricavo = ricavo + VALUES(ricavo)
                ");
            }
        }
    }

    $conn->query("TRUNCATE TABLE prenotazioni");
    header("Location: admin.php");
    exit();
}

// Get statistics - with error handling
$statistiche = ['Confermata' => 0, 'In attesa' => 0, 'Cancellata' => 0];

// Check if stato column exists
$stato_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'stato'")->num_rows > 0;

if ($stato_exists) {
    $totali = $conn->query("SELECT stato, COUNT(*) as totale FROM prenotazioni GROUP BY stato");
    if ($totali) {
        while ($row = $totali->fetch_assoc()) {
            $statistiche[$row['stato']] = $row['totale'];
        }
    }
} else {
    // If no stato column, just count total bookings
    $total_result = $conn->query("SELECT COUNT(*) as totale FROM prenotazioni");
    if ($total_result) {
        $total_row = $total_result->fetch_assoc();
        $statistiche['In attesa'] = $total_row['totale'];
    }
}

// Get recent bookings
$prenotazioni_query = "SELECT * FROM prenotazioni ORDER BY ";
// Check if we have data_prenotazione column
$data_col_exists = $conn->query("SHOW COLUMNS FROM prenotazioni LIKE 'data_prenotazione'")->num_rows > 0;
if ($data_col_exists) {
    $prenotazioni_query .= "data_prenotazione DESC, ";
}
$prenotazioni_query .= "id DESC LIMIT 10";

$prenotazioni = $conn->query($prenotazioni_query);

// Calculate total revenue
$totale_ricavi = 0;
$servizi_exists = $conn->query("SHOW TABLES LIKE 'servizi'")->num_rows > 0;

if ($servizi_exists && $stato_exists) {
    $entrate = $conn->query("SELECT SUM(s.prezzo) as totale FROM prenotazioni p JOIN servizi s ON p.servizio = s.nome WHERE p.stato = 'Confermata'");
    if ($entrate) {
        $entrate_row = $entrate->fetch_assoc();
        $totale_ricavi = $entrate_row['totale'] ?? 0;
    }
}

// Get revenue data
$ricavi_tutti_giorni = [];

// Check if storico_ricavi exists
$storico_exists = $conn->query("SHOW TABLES LIKE 'storico_ricavi'")->num_rows > 0;
if ($storico_exists) {
    $result_storico = $conn->query("SELECT data, ricavo FROM storico_ricavi");
    if ($result_storico) {
        while ($row = $result_storico->fetch_assoc()) {
            $ricavi_tutti_giorni[$row['data']] = floatval($row['ricavo']);
        }
    }
}

if ($servizi_exists && $stato_exists && $data_col_exists) {
    $result_ricavi = $conn->query("
        SELECT p.data_prenotazione, SUM(s.prezzo) as totale
        FROM prenotazioni p 
        JOIN servizi s ON p.servizio = s.nome
        WHERE p.stato = 'Confermata'
        GROUP BY p.data_prenotazione
    ");
    if ($result_ricavi) {
        while ($row = $result_ricavi->fetch_assoc()) {
            $data = $row['data_prenotazione'];
            $totale = floatval($row['totale']);
            if (isset($ricavi_tutti_giorni[$data])) {
                $ricavi_tutti_giorni[$data] += $totale;
            } else {
                $ricavi_tutti_giorni[$data] = $totale;
            }
        }
    }
}

krsort($ricavi_tutti_giorni);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard - Old School Barber</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
            color: #ffffff;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-header {
            padding: 0 2rem;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar.collapsed .sidebar-header {
            padding: 0 1.5rem;
            justify-content: center;
        }

        .sidebar-logo {
            font-size: 2rem;
            color: #d4af37;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            transition: opacity 0.3s ease;
        }

        .sidebar.collapsed .sidebar-title {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar-toggle {
            position: absolute;
            top: 1rem;
            right: -15px;
            width: 30px;
            height: 30px;
            background: #d4af37;
            border: none;
            border-radius: 50%;
            color: #1a1a2e;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: #ffd700;
            transform: scale(1.1);
        }

        .sidebar-nav {
            list-style: none;
            padding: 0 1rem;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: #a0a0a0;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            transform: translateX(5px);
        }

        .sidebar-nav i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        .sidebar.collapsed .sidebar-nav a {
            justify-content: center;
            padding: 1rem 0.5rem;
        }

        .sidebar.collapsed .sidebar-nav span {
            display: none;
        }

        /* Main Content */
        .main {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .main.expanded {
            margin-left: 80px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem 2rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ffffff;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(212, 175, 55, 0.1);
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: 1px solid rgba(212, 175, 55, 0.2);
        }

        .header-user i {
            color: #d4af37;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #d4af37, #ffd700);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.08);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #a0a0a0;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .stat-card .change {
            font-size: 0.85rem;
            color: #4ade80;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .content-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-card h3 i {
            color: #d4af37;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            font-weight: 600;
            color: #d4af37;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            color: #e0e0e0;
            font-weight: 400;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        /* Status Badges */
        .status {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status.confermata {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status.in-attesa {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .status.cancellata {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin: 0 0.2rem;
        }

        .action-btn.confirm {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .action-btn.cancel {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Forms */
        .form-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .form-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-card.success {
            border-color: rgba(34, 197, 94, 0.3);
            background: rgba(34, 197, 94, 0.05);
        }

        .form-card.danger {
            border-color: rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.05);
        }

        .form-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-card.success h3 {
            color: #4ade80;
        }

        .form-card.danger h3 {
            color: #f87171;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #d4af37;
            background: rgba(255, 255, 255, 0.12);
        }

        .form-group input::placeholder {
            color: #a0a0a0;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .submit-btn.success {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }

        .submit-btn.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        /* Error Messages */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        /* Success Messages */
        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .sidebar.mobile-open {
                transform: translateX(0);
            }

            .main {
                margin-left: 0;
            }

            .main.expanded {
                margin-left: 0;
            }

            .header {
                padding: 1rem;
            }

            .header-title {
                font-size: 1.4rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-section {
                grid-template-columns: 1fr;
            }

            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: #d4af37;
                border: none;
                border-radius: 8px;
                padding: 0.8rem;
                color: #1a1a2e;
                cursor: pointer;
                font-size: 1.2rem;
            }
        }

        @media (min-width: 769px) {
            .mobile-menu-btn {
                display: none;
            }
        }
    </style>
</head>
<body>

<button class="mobile-menu-btn" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-chevron-left"></i>
    </button>
    
    <div class="sidebar-header">
        <i class="fas fa-cut sidebar-logo"></i>
        <span class="sidebar-title">Admin Panel</span>
    </div>
    
    <ul class="sidebar-nav">
        <li><a href="#" class="active"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="#"><i class="fas fa-calendar-alt"></i><span>Prenotazioni</span></a></li>
        <li><a href="#"><i class="fas fa-scissors"></i><span>Servizi</span></a></li>
        <li><a href="#"><i class="fas fa-chart-line"></i><span>Report</span></a></li>
        <li><a href="#"><i class="fas fa-cog"></i><span>Impostazioni</span></a></li>
        <li><a href="index.php"><i class="fas fa-arrow-left"></i><span>Torna al sito</span></a></li>
    </ul>
</div>

<div class="main" id="main">
    <div class="header">
        <h1 class="header-title">Dashboard Amministratore</h1>
        <div class="header-user">
            <i class="fas fa-user-shield"></i>
            <span>Old School Barber Admin</span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Prenotazioni Confermate</h3>
            <div class="value"><?php echo $statistiche['Confermata']; ?></div>
            <div class="change">+12% dal mese scorso</div>
        </div>
        <div class="stat-card">
            <h3>In Attesa</h3>
            <div class="value"><?php echo $statistiche['In attesa']; ?></div>
            <div class="change">-5% dal mese scorso</div>
        </div>
        <div class="stat-card">
            <h3>Cancellate</h3>
            <div class="value"><?php echo $statistiche['Cancellata']; ?></div>
            <div class="change">-8% dal mese scorso</div>
        </div>
        <div class="stat-card">
            <h3>Ricavi Totali</h3>
            <div class="value">€<?php echo number_format($totale_ricavi, 2); ?></div>
            <div class="change">+18% dal mese scorso</div>
        </div>
    </div>

    <div class="content-grid">
        <div class="content-card">
            <h3><i class="fas fa-calendar-check"></i>Ultime Prenotazioni</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <?php if ($data_col_exists): ?>
                            <th>Data</th>
                            <?php endif; ?>
                            <th>Ora</th>
                            <th>Servizio</th>
                            <?php if ($stato_exists): ?>
                            <th>Stato</th>
                            <th>Azioni</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($prenotazioni && $prenotazioni->num_rows > 0): ?>
                            <?php $i = 1; while ($row = $prenotazioni->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($row['nome'] ?? 'N/A'); ?></td>
                                <?php if ($data_col_exists): ?>
                                <td>
                                    <?php 
                                    if (isset($row['data_prenotazione']) && $row['data_prenotazione']) {
                                        $data = date_create($row['data_prenotazione']);
                                        echo $data ? date_format($data, 'd/m/Y') : 'N/A';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <?php endif; ?>
                                <td><?php echo isset($row['orario']) ? date('H:i', strtotime($row['orario'])) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($row['servizio'] ?? 'N/A'); ?></td>
                                <?php if ($stato_exists): ?>
                                <td>
                                    <span class="status <?php 
                                        $stato = $row['stato'] ?? 'In attesa';
                                        if ($stato === 'Confermata') echo 'confermata'; 
                                        elseif ($stato === 'In attesa') echo 'in-attesa'; 
                                        elseif ($stato === 'Cancellata') echo 'cancellata'; 
                                    ?>">
                                        <?php echo htmlspecialchars($stato); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?action=confirm&id=<?php echo $row['id']; ?>" 
                                       class="action-btn confirm" 
                                       onclick="return confirm('Confermare questa prenotazione?')">
                                        <i class="fas fa-check"></i>Conferma
                                    </a>
                                    <a href="?action=cancel&id=<?php echo $row['id']; ?>" 
                                       class="action-btn cancel" 
                                       onclick="return confirm('Cancellare questa prenotazione?')">
                                        <i class="fas fa-times"></i>Cancella
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $stato_exists ? '7' : '4'; ?>" style="text-align: center; color: #a0a0a0;">
                                    Nessuna prenotazione trovata
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card">
            <h3><i class="fas fa-chart-line"></i>Report Ricavi</h3>
            <?php if (!empty($ricavi_tutti_giorni)): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr><th>Data</th><th>Ricavi</th></tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 0;
                            foreach ($ricavi_tutti_giorni as $data => $ricavo): 
                                if ($count >= 5) break; // Show only last 5 days
                                $count++;
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($data)); ?></td>
                                <td>€<?php echo number_format($ricavo, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color: #a0a0a0; text-align: center; padding: 2rem;">
                    Nessun dato sui ricavi disponibile
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-section">
        <div class="form-card success">
            <h3><i class="fas fa-plus-circle"></i>Aggiungi Servizio</h3>
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="nome_servizio" placeholder="Nome servizio" required />
                </div>
                <div class="form-group">
                    <input type="number" step="0.01" name="prezzo_servizio" placeholder="Prezzo (€)" required />
                </div>
                <button type="submit" name="add_service" class="submit-btn success">
                    <i class="fas fa-plus"></i> Aggiungi Servizio
                </button>
            </form>
        </div>

        <div class="form-card danger">
            <h3><i class="fas fa-trash-alt"></i>Gestione Prenotazioni</h3>
            <p style="color: #f87171; margin-bottom: 1rem; font-size: 0.9rem;">
                Attenzione: questa azione cancellerà tutte le prenotazioni e salverà i ricavi nello storico.
            </p>
            <form method="POST">
                <button type="submit" name="clear_prenotazioni" class="submit-btn danger" 
                        onclick="return confirm('Vuoi davvero svuotare tutte le prenotazioni? I ricavi verranno salvati nello storico.')">
                    <i class="fas fa-trash"></i> Svuota Prenotazioni
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    let sidebarCollapsed = false;
    let mobileOpen = false;

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            mobileOpen = !mobileOpen;
            sidebar.classList.toggle('mobile-open', mobileOpen);
        } else {
            sidebarCollapsed = !sidebarCollapsed;
            sidebar.classList.toggle('collapsed', sidebarCollapsed);
            main.classList.toggle('expanded', sidebarCollapsed);
            
            const toggleIcon = document.querySelector('.sidebar-toggle i');
            toggleIcon.className = sidebarCollapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
        }
    }

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', (e) => {
        const sidebar = document.getElementById('sidebar');
        const mobileBtn = document.querySelector('.mobile-menu-btn');
        
        if (window.innerWidth <= 768 && mobileOpen && 
            !sidebar.contains(e.target) && 
            !mobileBtn.contains(e.target)) {
            toggleSidebar();
        }
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            mobileOpen = false;
        }
    });
</script>
</body>
</html>