<?php
date_default_timezone_set('Europe/Rome'); // Imposta fuso orario italiano
include 'connessione.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Prenota Barbiere</title>
    <style>
        /* FONT San Francisco - sistema Apple e fallback */
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen,
                Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #f0f4f8;
        }
        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            padding: 40px 45px;
            width: 380px;
            max-width: 90vw;
            border: 1.5px solid rgba(255,255,255,0.25);
            transition: transform 0.3s ease;
        }
        .form-container:hover {
            transform: scale(1.02);
            box-shadow: 0 15px 35px 0 rgba(31, 38, 135, 0.6);
        }
        form h1 {
            margin-bottom: 30px;
            font-weight: 900;
            font-size: 2.2rem;
            letter-spacing: 1.1px;
            text-align: center;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
            user-select: none;
        }
        input, select, button {
            width: 100%;
            padding: 14px 20px;
            margin-bottom: 22px;
            border-radius: 12px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            color: #222;
            box-sizing: border-box;
            transition: box-shadow 0.25s ease, transform 0.25s ease;
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        input::placeholder, select option:first-child {
            color: #888;
            font-weight: 500;
        }
        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 8px 3px #667eea;
            transform: translateY(-2px);
            background: white;
        }
        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20width%3D%2214%22%20height%3D%2210%22%20viewBox%3D%220%200%2014%2010%22%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%3E%3Cpath%20d%3D%22M1%200l6%206%206-6%22%20stroke%3D%22%236678ea%22%20stroke-width%3D%222%22%20fill%3D%22none%22%20fill-rule%3D%22evenodd%22/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px 10px;
            padding-right: 48px;
            cursor: pointer;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-weight: 700;
            border-radius: 14px;
            cursor: pointer;
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.6);
            user-select: none;
            transition: background 0.3s ease, transform 0.2s ease;
            border: none;
            letter-spacing: 1px;
        }
        button:hover {
            background: linear-gradient(135deg, #5a6edc 0%, #623f9a 100%);
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(102, 126, 234, 0.8);
        }
        .admin-link {
            text-align: center;
            margin-top: 12px;
        }
        .admin-link a {
            color: #c3c6f1;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
            user-select: none;
        }
        .admin-link a:hover {
            color: #e0e2ff;
            text-decoration: underline;
        }

        /* Scrollbar minimal per selezione orario */
        select[name="orario"]::-webkit-scrollbar {
            width: 8px;
        }
        select[name="orario"]::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        select[name="orario"]::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 8px;
        }

    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const dateInput = document.querySelector('input[name="data_prenotazione"]');

            // Calcola la data locale formattata yyyy-mm-dd
            const today = new Date();
            const day = String(today.getDate()).padStart(2, '0');
            const month = String(today.getMonth() + 1).padStart(2, '0'); // Gennaio=0
            const year = today.getFullYear();
            const todayLocal = `${year}-${month}-${day}`;

            dateInput.setAttribute("min", todayLocal);
            dateInput.value = todayLocal;  // imposta valore di default a oggi (locale)

            dateInput.addEventListener('input', () => {
                const selected = new Date(dateInput.value);
                if (selected.getDay() === 1) {
                    dateInput.value = ""; // blocca lunedì (giorno 1 = lunedì)
                }
            });

            const timeSelect = document.querySelector('select[name="orario"]');
            for (let hour = 9; hour < 19; hour++) {
                for (let min of [0, 30]) {
                    let h = hour.toString().padStart(2, '0');
                    let m = min.toString().padStart(2, '0');
                    let option = document.createElement('option');
                    option.value = `${h}:${m}`;
                    option.textContent = `${h}:${m}`;
                    timeSelect.appendChild(option);
                }
            }
        });
    </script>
</head>
<body>
    <div class="form-container" role="main" aria-label="Modulo prenotazione taglio barbiere">
        <form method="POST" action="prenota.php" autocomplete="off">
            <h1>Prenota il tuo taglio</h1>
            <input type="text" name="nome" placeholder="Nome" required autocomplete="name" aria-label="Nome">
            <input type="email" name="email" placeholder="Email" autocomplete="email" aria-label="Email">
            <input type="tel" name="telefono" placeholder="Telefono" autocomplete="tel" aria-label="Telefono">
            <select name="servizio" required aria-label="Seleziona servizio">
                <option value="" disabled selected>Seleziona servizio</option>
                <?php
                $query = $conn->query("SELECT nome, prezzo FROM servizi");
                while ($row = $query->fetch_assoc()) {
                    echo "<option value='{$row['nome']}'>{$row['nome']} - €{$row['prezzo']}</option>";
                }
                ?>
            </select>
            <input type="date" name="data_prenotazione" required aria-label="Data prenotazione">
            <select name="orario" required aria-label="Seleziona orario"></select>
            <button type="submit" aria-label="Prenota il taglio">Prenota</button>
            <div class="admin-link"><a href="login.php" aria-label="Area amministratore">Area Admin</a></div>
        </form>
    </div>
</body>
</html>
