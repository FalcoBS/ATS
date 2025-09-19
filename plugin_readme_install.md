# Advanced Threads System - WordPress Plugin

Un sistema completo di forum/threads per WordPress con funzionalità avanzate come upvoting, profili utente, sistema di follow e molto altro.

## Caratteristiche Principali

### 🚀 Funzionalità Core
- **Sistema Thread completo** - Creazione, modifica, eliminazione thread
- **Sistema Reply nidificato** - Risposte con supporto per thread illimitati
- **Voting System** - Upvote/downvote per thread e reply
- **Profili Utente Avanzati** - Statistiche, badge, followers
- **Sistema Follow** - Follow utenti, thread, categorie
- **Notifiche Real-time** - Sistema completo di notifiche
- **Moderazione Avanzata** - Tools per amministratori e moderatori

### 🎨 UI/UX
- **Design Moderno** - Interfaccia simile a Simple Flying
- **Responsive Design** - Ottimizzato per mobile e desktop
- **Dark Mode Support** - Supporto tema scuro automatico
- **Animazioni Fluide** - Micro-interazioni e feedback visivi
- **Accessibility** - WCAG 2.1 compliant

### ⚡ Performance
- **Database Ottimizzato** - Schema efficiente con indici appropriati
- **AJAX Loading** - Caricamento asincrono senza refresh pagina
- **Caching Ready** - Compatibile con plugin di caching
- **Infinite Scroll** - Caricamento automatico contenuti
- **Image Optimization** - Compressione e ridimensionamento automatico

### 🔧 Configurazione
- **40+ Opzioni** - Pannello admin completo
- **Shortcodes** - Integrazione facile in pagine esistenti
- **Template Override** - Personalizzazione completa template
- **Hook System** - API per sviluppatori
- **Multisite Ready** - Supporto network WordPress

## Requisiti Sistema

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+ o MariaDB 10.2+
- Memoria PHP: 128MB (consigliato 256MB)
- Spazio disco: 10MB

## Installazione

### Metodo 1: Manuale
1. Scarica il plugin e estrai nella cartella `/wp-content/plugins/`
2. Attiva il plugin dalla dashboard WordPress
3. Vai in `Threads > Settings` per la configurazione iniziale
4. Crea le tue prime categorie
5. Il plugin è pronto!

### Metodo 2: Via ZIP
1. Vai in `Plugin > Aggiungi nuovo > Carica plugin`
2. Seleziona il file ZIP del plugin
3. Attiva il plugin
4. Segui la configurazione guidata

## Configurazione Iniziale

### 1. Impostazioni Generali
```
- Threads per pagina: 20
- Reply per pagina: 50  
- Abilita voting: ✓
- Abilita following: ✓
- Editor ricco: ✓
```

### 2. Permessi Utenti
```
- Chi può creare thread: Subscriber+
- Chi può rispondere: Subscriber+  
- Chi può votare: Subscriber+
- Moderazione automatica: ✗
```

### 3. Pagine Create Automaticamente
Il plugin crea automaticamente queste pagine:
- `/threads/` - Lista threads principali
- `/profile/[username]/` - Profili utente
- `/create-thread/` - Form creazione thread
- `/leaderboard/` - Classifica utenti

### 4. Shortcodes Disponibili
```php
[ats_threads_listing] // Lista threads
[ats_user_profile] // Profilo utente
[ats_create_thread_form] // Form creazione
[ats_leaderboard] // Classifica
[ats_thread_categories] // Lista categorie
[ats_recent_replies] // Reply recenti
[ats_user_stats user_id="123"] // Statistiche utente
```

## Struttura Directory Plugin

```
advanced-threads-system/
├── advanced-threads-system.php    # File principale
├── uninstall.php                  # Script disinstallazione
├── includes/                      # Classi core
│   ├── class-ats-core.php
│   ├── class-ats-installer.php
│   ├── class-ats-thread-manager.php
│   ├── class-ats-user-manager.php
│   ├── class-ats-vote-manager.php
│   ├── class-ats-ajax-handler.php
│   └── functions.php
├── admin/                         # Pannello amministrazione
│   ├── class-ats-admin.php
│   ├── class-ats-settings.php
│   └── views/
├── public/                        # Frontend
│   ├── class-ats-frontend.php
│   ├── class-ats-templates.php
│   └── class-ats-enqueue.php
├── templates/                     # Template files
│   ├── single-thread.php
│   ├── archive-thread.php
│   ├── user-profile.php
│   └── parts/
├── assets/                        # CSS/JS/Images
│   ├── css/
│   ├── js/
│   └── images/
└── languages/                     # Translations
```

## Personalizzazione Template

### Override Template nel Tema
Crea la cartella `advanced-threads` nel tuo tema:
```
il-tuo-tema/
├── advanced-threads/
│   ├── single-thread.php
│   ├── archive-thread.php
│   └── parts/
│       ├── thread-card.php
│       └── reply-item.php
```

### Hook per Sviluppatori
```php
// Filtri disponibili
add_filter('ats_thread_content', 'my_custom_thread_content');
add_filter('ats_user_can_vote', 'my_voting_permission');
add_filter('ats_notification_message', 'my_custom_notifications');

// Azioni disponibili  
add_action('ats_thread_created', 'my_thread_created_handler');
add_action('ats_reply_posted', 'my_reply_posted_handler');
add_action('ats_vote_cast', 'my_vote_handler');
```

## Database Schema

Il plugin crea 9 tabelle nel database:

### Tabelle Principali
- `wp_ats_threads` - Thread principali
- `wp_ats_replies` - Risposte e commenti  
- `wp_ats_votes` - Sistema voting
- `wp_ats_user_profiles` - Profili utente estesi

### Tabelle di Supporto
- `wp_ats_follows` - Sistema follow
- `wp_ats_categories` - Categorie thread
- `wp_ats_notifications` - Sistema notifiche
- `wp_ats_thread_views` - Analytics visualizzazioni
- `wp_ats_reports` - Sistema segnalazioni

## Performance e Ottimizzazione

### Cache Recommendations
```php
// W3 Total Cache
define('W3TC_DYNAMIC_SECURITY', true);

// WP Rocket  
add_filter('rocket_cache_reject_uri', function($uris) {
    $uris[] = '/threads/';
    $uris[] = '/profile/';
    return $uris;
});
```

### Database Optimization
- Tutti gli indici necessari sono creati automaticamente
- Cleanup automatico ogni 24 ore via cron
- Query ottimizzate con preparazione statement

## Sicurezza

### Misure Implementate
- Sanitizzazione completa input utenti
- Nonce verification per AJAX
- Capability checks per ogni azione
- XSS protection
- SQL injection prevention
- CSRF protection

### Configurazione Sicurezza
```php
// functions.php del tema
add_filter('ats_allowed_html', function($allowed) {
    // Personalizza HTML permessi nei thread
    return $allowed;
});
```

## Troubleshooting

### Problemi Comuni

#### Tabelle non create
```sql
-- Verifica tabelle esistenti
SHOW TABLES LIKE 'wp_ats_%';

-- Se mancanti, riattiva plugin
```

#### AJAX non funziona
```php
// Verifica in console browser
console.log(ats_ajax); // Deve mostrare oggetto config

// Controlla permessi file
chmod 644 assets/js/threads-frontend.js
```

#### Template non caricati
```php
// Debug template loading
add_action('wp_head', function() {
    if (is_singular('ats_thread')) {
        echo '<!-- ATS Debug: Thread template loaded -->';
    }
});
```

#### Performance lente
```php
// Abilita query debug
define('WP_DEBUG', true);
define('SAVEQUERIES', true);

// Controlla slow queries
add_action('wp_footer', function() {
    if (current_user_can('administrator')) {
        global $wpdb;
        echo '<pre>' . print_r($wpdb->queries, true) . '</pre>';
    }
});
```

## Changelog

### v1.0.0 (Data Rilascio)
- Rilascio iniziale
- Sistema thread completo
- Voting e profili utenti  
- Template responsive
- Pannello admin
- 9 tabelle database ottimizzate

## Supporto e Contributi

### Documentazione Completa
- Wiki GitHub: [Link da aggiungere]
- Video tutorials: [Link da aggiungere] 
- Demo live: [Link da aggiungere]

### Segnalazione Bug
1. Controlla se il bug è già stato segnalato
2. Crea una issue dettagliata su GitHub
3. Includi: versione WordPress, PHP, plugin attivi
4. Allega screenshot se possibile

### Richieste Feature
- Usa il template feature request su GitHub
- Spiega il caso d'uso specifico
- Vota le feature esistenti che ti interessano

### Contribuire al Codice
```bash
git clone https://github.com/username/advanced-threads-system
cd advanced-threads-system
composer install
npm install
npm run dev
```

## Licenza

GPL v2 or later - Usa e modifica liberamente rispettando i termini della licenza.

## Credits

Sviluppato con ❤️ per la community WordPress italiana.

**Librerie utilizzate:**
- jQuery (incluso in WordPress)
- Chart.js per statistiche
- Quill.js per editor ricco
- Intersection Observer API per infinite scroll

---

## Prossimi Passi Implementazione

Ecco la sequenza consigliata per implementare il plugin:

### Fase 1: Setup Base (1-2 giorni)
1. Crea la struttura directory
2. Copia il file principale `advanced-threads-system.php`
3. Implementa `ATS_Installer` e `ATS_Core`
4. Testa attivazione e creazione tabelle

### Fase 2: Post Types e Backend (2-3 giorni)  
1. Implementa `ATS_Post_Types`
2. Crea `ATS_Thread_Manager`
3. Sviluppa `ATS_User_Manager`
4. Implementa `ATS_Vote_Manager`
5. Testa operazioni CRUD di base

### Fase 3: AJAX e Interazioni (3-4 giorni)
1. Sviluppa `ATS_AJAX_Handler`
2. Crea il JavaScript frontend
3. Implementa sistema voting
4. Sviluppa sistema follow/unfollow
5. Testa tutte le interazioni AJAX

### Fase 4: Templates e Frontend (3-4 giorni)
1. Crea template `single-thread.php`
2. Sviluppa `archive-thread.php`
3. Implementa `user-profile.php`
4. Crea i partial templates
5. Applica CSS e responsive design

### Fase 5: Admin Panel (2-3 giorni)
1. Implementa `ATS_Admin`
2. Crea pagina settings
3. Sviluppa tools moderazione
4. Implementa dashboard widgets
5. Testa configurazioni

### Fase 6: Features Avanzate (3-5 giorni)
1. Sistema notifiche
2. Shortcodes
3. SEO e structured data
4. Sistema reports/moderazione
5. Analytics e statistiche

### Fase 7: Testing e Optimization (2-3 giorni)
1. Test cross-browser
2. Performance optimization
3. Security audit
4. Bug fixing
5. Documentazione finale

### Tempo Totale Stimato: 15-25 giorni

---

## Quick Start per Sviluppatori

Se vuoi iniziare subito con lo sviluppo:

### 1. Crea la struttura base
```bash
mkdir advanced-threads-system
cd advanced-threads-system
mkdir -p includes admin public templates assets/{css,js,images} languages
```

### 2. Copia i file principali dalla documentazione
- `advanced-threads-system.php` (file main)
- `includes/class-ats-installer.php`
- `includes/class-ats-core.php`

### 3. Test di attivazione
```php
// Aggiungi debug temporaneo
add_action('activated_plugin', function($plugin) {
    if ($plugin === plugin_basename(__FILE__)) {
        error_log('ATS Plugin activated successfully');
    }
});
```

### 4. Verifica database
Dopo l'attivazione, controlla che tutte le tabelle siano state create:
```sql
SHOW TABLES LIKE 'wp_ats_%';
```

Dovresti vedere 9 tabelle create.

### 5. Test delle funzionalità base
Crea un thread di test tramite codice:
```php
// Aggiungi in functions.php temporaneamente
add_action('init', function() {
    if (isset($_GET['ats_test'])) {
        $thread_manager = new ATS_Thread_Manager();
        $result = $thread_manager->create_thread(array(
            'title' => 'Test Thread',
            'content' => 'Questo è un thread di test',
            'author_id' => 1,
            'category' => 'general-discussion'
        ));
        
        wp_die('Thread created with ID: ' . $result);
    }
});
```

Poi visita: `yoursite.com/?ats_test=1`

Questo ti darà una base solida per iniziare lo sviluppo del sistema completo!

## Note Finali

Hai ora tutti gli elementi per creare un sistema di thread avanzato:

- **Architettura scalabile** - Plugin ben strutturato
- **Database ottimizzato** - Schema efficiente con indici
- **Frontend moderno** - JavaScript e CSS responsive  
- **Backend completo** - AJAX, voting, profili, notifiche
- **Estendibilità** - Hook system per personalizzazioni

La struttura plugin ti permette di:
- Mantenere tutto organizzato
- Disattivare senza perdere funzionalità tema
- Aggiornare facilmente
- Condividere o vendere il plugin

Inizia con le basi (database + post types) e aggiungi gradualmente le funzionalità avanzate. Il risultato finale sarà un sistema paragonabile a Simple Flying ma completamente personalizzabile.