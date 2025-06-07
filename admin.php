<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard - Barber</title>
  <link rel="stylesheet" href="dashboard.css" />
  <style>
    /* Modifica per bloccare la sidebar */
    .sidebar {
      position: fixed !important;
      top: 0;
      left: 0;
      height: 100vh !important;
      z-index: 9999;
    }
    .main {
      margin-left: 60px; /* Adatta questo valore alla larghezza della sidebar */
    }
/* Stile per il nuovo box "aggiungi servizio" */
.service-form-box {
  border: 2px solid #28a745;
  padding: 20px;
  margin: 30px 20px 0 0; /* margine sopra e a destra */
  border-radius: 10px;
  background-color: #e8f5e9;
  box-shadow: 0 0 10px rgba(40,167,69,0.3);
  max-width: 350px;
  margin-left: auto; /* spinge il box a destra */
}

  /* niente centratura */
}

.service-form-box h3 {
  margin-top: 0;
  margin-bottom: 15px;
  text-align: center;
}

.service-form-box form input,
.service-form-box form button {
  width: 100%;
  max-width: 300px;
  margin: 0 auto 10px auto;
  border-radius: 5px;
  border: 1px solid #ccc;
  font-size: 14px;
  box-sizing: border-box;
  display: block;
  padding: 8px;
}

.service-form-box form button {
  background-color: #28a745;
  color: white;
  font-weight: bold;
  border: none;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.service-form-box form button:hover {
  background-color: #218838;
}


    /* Stile per il nuovo box "Gestione Prenotazioni" */
.manage-prenotazioni-box {
  border: 2px solid #e74c3c;
  padding: 20px;
  margin-top: 30px;
  border-radius: 10px;
  background-color: #fdecea;
  box-shadow: 0 0 10px rgba(231, 76, 60, 0.3);
  max-width: 350px; /* come aggiungi servizio */
  margin-left: auto; /* sposta a destra */
  margin-right: 20px; /* margine a destra */
}

    .manage-prenotazioni-box h3 {
      margin-top: 0;
      margin-bottom: 15px;
      color: #e74c3c;
    }
    .manage-prenotazioni-box form button {
      width: 100%;
      background: #e74c3c;
      color: white;
      padding: 10px;
      font-weight: bold;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .manage-prenotazioni-box form button:hover {
      background-color: #c0392b;
    }

    /* Aggiunta colore per gli stati */
    .confermata {
      color: green;
      font-weight: bold;
    }
    .in-attesa {
      color: orange;
      font-weight: bold;
    }
    .cancellata {
      color: red;
      font-weight: bold;
    }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php
  session_start();
  if (!isset($_SESSION['logged'])) {
    header("Location: login.php");
    exit();
  }
  include 'connessione.php';

  if (isset($_GET['action']) && isset($_GET['id'])) {
      $id = intval($_GET['id']);
      if ($_GET['action'] === 'confirm') {
          $conn->query("UPDATE prenotazioni SET stato = 'Confermata' WHERE id = $id");
      } elseif ($_GET['action'] === 'cancel') {
          $conn->query("UPDATE prenotazioni SET stato = 'Cancellata' WHERE id = $id");
      }
      header("Location: admin.php");
      exit();
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
      $nome = $conn->real_escape_string(trim($_POST['nome_servizio']));
      $prezzo = floatval($_POST['prezzo_servizio']);
      if ($nome !== '' && $prezzo > 0) {
          $conn->query("INSERT INTO servizi (nome, prezzo) VALUES ('$nome', $prezzo)");
          header("Location: admin.php");
          exit();
      } else {
          $error_message = "Inserisci un nome valido e un prezzo maggiore di 0.";
      }
  }

if (isset($_POST['clear_prenotazioni'])) {
    // Salva i ricavi giornalieri prima di svuotare la tabella
    $ricavi_query = $conn->query("
        SELECT p.data_prenotazione, SUM(s.prezzo) as totale
        FROM prenotazioni p 
        JOIN servizi s ON p.servizio = s.nome
        WHERE p.stato = 'Confermata'
        GROUP BY p.data_prenotazione
    ");

    while ($row = $ricavi_query->fetch_assoc()) {
        $data = $conn->real_escape_string($row['data_prenotazione']);
        $totale = floatval($row['totale']);
        // Inserisci o aggiorna nel report storico
        $conn->query("
            INSERT INTO storico_ricavi (data, ricavo)
            VALUES ('$data', $totale)
            ON DUPLICATE KEY UPDATE ricavo = ricavo + VALUES(ricavo)
        ");
    }

    // Svuota tutte le prenotazioni
    $conn->query("TRUNCATE TABLE prenotazioni");

    header("Location: admin.php");
    exit();
}



  $totali = $conn->query("SELECT stato, COUNT(*) as totale FROM prenotazioni GROUP BY stato");
  $statistiche = ['Confermata' => 0, 'In attesa' => 0, 'Cancellata' => 0];
  while ($row = $totali->fetch_assoc()) {
    $statistiche[$row['stato']] = $row['totale'];
  }

$prenotazioni = $conn->query("SELECT * FROM prenotazioni ORDER BY data_prenotazione DESC, orario DESC LIMIT 5");


  $entrate = $conn->query("SELECT SUM(s.prezzo) as totale FROM prenotazioni p JOIN servizi s ON p.servizio = s.nome WHERE p.stato = 'Confermata'");
  $totale_ricavi = $entrate->fetch_assoc()['totale'] ?? 0;

// Ricavi giornalieri: da storico_ricavi + prenotazioni confermate attuali
$ricavi_tutti_giorni = [];

// Carica i ricavi storici
$result_storico = $conn->query("SELECT data, ricavo FROM storico_ricavi");
while ($row = $result_storico->fetch_assoc()) {
    $ricavi_tutti_giorni[$row['data']] = floatval($row['ricavo']);
}

// Aggiungi anche le prenotazioni confermate attuali (non ancora salvate nello storico)
$result_ricavi = $conn->query("
    SELECT p.data_prenotazione, SUM(s.prezzo) as totale
    FROM prenotazioni p 
    JOIN servizi s ON p.servizio = s.nome
    WHERE p.stato = 'Confermata'
    GROUP BY p.data_prenotazione
");
while ($row = $result_ricavi->fetch_assoc()) {
    $data = $row['data_prenotazione'];
    $totale = floatval($row['totale']);
    if (isset($ricavi_tutti_giorni[$data])) {
        $ricavi_tutti_giorni[$data] += $totale;
    } else {
        $ricavi_tutti_giorni[$data] = $totale;
    }
}

// Ordina per data (opzionale)
krsort($ricavi_tutti_giorni);

?>
<div class="sidebar">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <h2>BS</h2>
  <ul>
    <li><i class="fas fa-home"></i></li>
    <li><i class="fas fa-calendar-alt"></i></li>
    <li><i class="fas fa-cut"></i></li>
    <li><i class="fas fa-cog"></i></li>
  </ul>
</div>
<div class="main">
  <div class="topbar">
    <input type="text" placeholder="Search..." />
    <div class="user">Old school barber</div>
  </div>
  <div class="cards">
    <div class="card"><h3>Confirmed</h3><p><?php echo $statistiche['Confermata']; ?></p></div>
    <div class="card"><h3>Pending</h3><p><?php echo $statistiche['In attesa']; ?></p></div>
    <div class="card"><h3>Canceled</h3><p><?php echo $statistiche['Cancellata']; ?></p></div>
  </div>
  <div class="content">
    <div class="left">
      <h3>Last Reservations</h3>
      <table>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Data</th>
          <th>Ora</th>
          <th>Servizio</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
        <?php $i = 1; while ($row = $prenotazioni->fetch_assoc()): ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($row['nome']); ?></td>
            <td>
              <?php 
                $data = date_create($row['data_prenotazione']);
                echo $data ? date_format($data, 'd/m/Y') : '';
              ?>
            </td>
            <td><?php echo date('H:i', strtotime($row['orario'])); ?></td>

            <td><?php echo htmlspecialchars($row['servizio']); ?></td>
            <td>
              <span class="<?php 
                if ($row['stato'] === 'Confermata') echo 'confermata'; 
                elseif ($row['stato'] === 'In attesa') echo 'in-attesa'; 
                elseif ($row['stato'] === 'Cancellata') echo 'cancellata'; 
              ?>">
                <?php echo htmlspecialchars($row['stato']); ?>
              </span>
            </td>
            <td>
              <a href="?action=confirm&id=<?php echo $row['id']; ?>" onclick="return confirm('Confermare questa prenotazione?')">✔️ Conferma</a>
              <a href="?action=cancel&id=<?php echo $row['id']; ?>" onclick="return confirm('Cancellare questa prenotazione?')">❌ Cancella</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>

    <div class="right">
      <h3>Report Completo Guadagni Giornalieri</h3>
      <label for="selectData">Seleziona giorno:</label>
      <select id="selectData">
        <?php 
          $dates_sorted = array_keys($ricavi_tutti_giorni);
          sort($dates_sorted);
          foreach ($dates_sorted as $date):
        ?>
          <option value="<?php echo $date; ?>"><?php echo date('d/m/Y', strtotime($date)); ?></option>
        <?php endforeach; ?>
      </select>
      <table id="reportTable" style="margin-top:15px;">
        <thead>
          <tr><th>Data</th><th>Ricavi (€)</th></tr>
        </thead>
        <tbody>
          <tr>
            <?php 
              $firstDate = $dates_sorted[0] ?? null;
              $firstRevenue = $firstDate ? $ricavi_tutti_giorni[$firstDate] : 0;
            ?>
            <td><?php echo $firstDate ? date('d/m/Y', strtotime($firstDate)) : ''; ?></td>
            <td>€<?php echo number_format($firstRevenue, 2); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- BOX DEL FORM SEZIONE SERVIZI -->
  <div class="service-form-box">
    <h3>Aggiungi Servizio</h3>
    <?php if (!empty($error_message)): ?>
      <p style="color:red;"><?php echo $error_message; ?></p>
    <?php endif; ?>
    <form method="POST">
      <input type="text" name="nome_servizio" placeholder="Nome servizio" required />
      <input type="number" step="0.01" name="prezzo_servizio" placeholder="Prezzo (€)" required />
      <button type="submit" name="add_service">Aggiungi</button>
    </form>
  </div>

  <!-- BOX GESTIONE PRENOTAZIONI -->
  <div class="manage-prenotazioni-box">
    <h3>Gestione Prenotazioni</h3>
    <form method="POST">
      <button type="submit" name="clear_prenotazioni" onclick="return confirm('Vuoi davvero svuotare tutte le prenotazioni?')">Svuota Prenotazioni</button>
    </form>
  </div>
</div>

<script>
  const selectData = document.getElementById('selectData');
  const reportTableBody = document.querySelector('#reportTable tbody');

  selectData.addEventListener('change', () => {
    const selectedDate = selectData.value;
    fetch('ricavi_giornalieri.php?data=' + selectedDate)
      .then(response => response.json())
      .then(data => {
        reportTableBody.innerHTML = `<tr><td>${data.data_formattata}</td><td>€${data.ricavo.toFixed(2)}</td></tr>`;
      });
  });
</script>
</body>
</html>
