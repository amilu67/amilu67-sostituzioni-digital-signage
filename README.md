# amilu67 Sostituzioni Digital Signage

**Versione:** 1.2.0  
**Autore:** amilu67  
**Slug WordPress.org:** `amilu67-sostituzioni-digital-signage`

Plugin WordPress per la gestione delle sostituzioni dei docenti con visualizzazione Digital Signage su schermo grande, progettato per istituti scolastici e compatibile con siti basati su Design Scuole Italia.

## Funzioni principali

- Anagrafica docenti.
- Editor tabellare settimanale dell'orario, da lunedì a sabato, fino a 12 ore al giorno.
- Gestione di lezioni, classi, materie, aule e ore di disponibilità.
- Importazione CSV per singolo docente e in modalità bulk.
- Registrazione delle assenze dei docenti con generazione automatica delle ore da coprire.
- Gestione delle classi assenti con liberazione automatica dei docenti interessati.
- Suggerimento dei sostituti in base a disponibilità e docenti liberati.
- Controlli contro sovrapposizioni, assenze e doppie assegnazioni.
- Prospetto giornaliero delle sostituzioni.
- Digital Signage autonomo su `/amilu67-sostituzioni-schermo/`, aggiornato via REST API.
- Modalità a tutta finestra e pulsante fullscreen per monitor e TV.
- Shortcode `[amilu67_sds_sostituzioni]` per incorporare il tabellone in una pagina WordPress.
- Stile isolato e compatibile con Design Scuole Italia.

## Nome e slug proposti per WordPress.org

In risposta alla review WordPress.org, la versione 1.2.0 usa un nome distintivo e richiede la nuova riserva dello slug:

- cartella: `amilu67-sostituzioni-digital-signage/`
- file principale: `amilu67-sostituzioni-digital-signage.php`
- Text Domain: `amilu67-sostituzioni-digital-signage`

La versione 1.2.0 usa il prefisso univoco `amilu67_sds_` per dichiarazioni e dati del plugin. All'attivazione, eventuali dati creati dalle versioni di prova precedenti vengono copiati automaticamente nelle nuove tabelle/opzioni prefissate, lasciando intatti i dati legacy per sicurezza.

## Aggiornamento dalle build di prova precedenti

Poiché cambiano cartella, slug e prefissi tecnici, WordPress considera il nuovo pacchetto distinto dalle build di prova precedenti. All'attivazione il plugin crea le nuove tabelle `amilu67_sds_*` e copia automaticamente i dati legacy disponibili. I dati precedenti non vengono cancellati.

Procedura consigliata:

1. Fare un backup del sito/database.
2. Disattivare la build precedente del plugin.
3. Installare e attivare il nuovo ZIP con cartella `amilu67-sostituzioni-digital-signage`.
4. Verificare Docenti, Orario, Assenze, Sostituzioni e Impostazioni schermo.
5. Solo dopo la verifica, rimuovere la vecchia cartella del plugin se ancora presente.

Non attivare contemporaneamente una build precedente e la versione 1.2.0.

## Import CSV singolo docente

Colonne supportate:

`giorno;ora;inizio;fine;classe;materia;aula;tipo`

`tipo` può essere `lezione` oppure `disponibilita`.

## Import CSV bulk

Colonne supportate:

`codice_docente;nome;cognome;email;giorno;ora;inizio;fine;classe;materia;aula;tipo`

## Compatibilità

- WordPress 6.5+
- PHP 8.0+
- Design Scuole Italia

## Privacy

Il monitor non tratta dati degli studenti. Per i docenti è disponibile anche il formato `Cognome + iniziale`.

## Novità 1.2.0

- Richiesta del nuovo slug WordPress.org `amilu67-sostituzioni-digital-signage` con nome distintivo e prefisso tecnico univoco.
- Nome distintivo: `amilu67 Sostituzioni Digital Signage`.
- Nuovo slug richiesto: `amilu67-sostituzioni-digital-signage`.
- Prefisso tecnico univoco `amilu67_sds_` applicato alle dichiarazioni e ai dati del plugin.
- Migrazione automatica dei dati dalle build di prova precedenti.
