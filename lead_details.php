<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/log/error.log');

// Require the Database class
require_once __DIR__ . '/Database.php';

// Set up logging
$logFolder = __DIR__ . '/log';
if (!file_exists($logFolder)) {
    mkdir($logFolder, 0777, true);
}
$errorLog = $logFolder . '/error.log';
$activityLog = $logFolder . '/activity.log'; // Define activityLog

// Function to log messages
function logMessage($logFile, $message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Database setup
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    logMessage($errorLog, 'Database connection failed: ' . $e->getMessage());
    die('Database connection failed');
}

$lead = null;
$calls = [];

if (isset($_GET['leadsid']) && !empty($_GET['leadsid'])) {
    $leadsid = $_GET['leadsid'];
    logMessage($activityLog, "lead_details.php: Received leadsid: " . $leadsid);

    // Fetch lead details
    $sql = 'SELECT * FROM leads WHERE leadsid = ?';
    logMessage($activityLog, "lead_details.php: Executing query: " . $sql . " with leadsid: " . $leadsid);
    $params = [$leadsid];
    $stmt = $db->query($sql, $params);
    $lead = $db->fetch($stmt);

    if ($lead) {
        logMessage($activityLog, "lead_details.php: Lead found: " . print_r($lead, true));
    } else {
        logMessage($activityLog, "lead_details.php: Lead not found for leadsid: " . $leadsid);
    }

    // Fetch all calls for this lead
    $sqlCalls = 'SELECT * FROM calls WHERE leadsid = ? ORDER BY timestamp DESC';
    $stmtCalls = $db->query($sqlCalls, $params);
    $calls = $db->fetchAll($stmtCalls);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Lead Details</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/overlayscrollbars/1.13.1/css/OverlayScrollbars.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
  <!-- Custom styles for dark mode toggle (if needed, AdminLTE handles most) -->
  <style>
    body.dark-mode .main-header, body.dark-mode .main-sidebar, body.dark-mode .main-footer, body.dark-mode .content-wrapper {
      background-color: #343a40 !important;
      color: #dee2e6 !important;
    }
    body.dark-mode .card {
      background-color: #454d55 !important;
      color: #dee2e6 !important;
      border-color: #6c757d !important;
    }
    body.dark-mode .card-header, body.dark-mode .card-body, body.dark-mode .card-footer {
      background-color: #454d55 !important;
      color: #dee2e6 !important;
    }
    body.dark-mode .table {
      color: #dee2e6 !important;
    }
    body.dark-mode .table thead th {
      border-bottom-color: #6c757d !important;
    }
    body.dark-mode .table td, body.dark-mode .table th {
      border-top-color: #6c757d !important;
    }
    body.dark-mode .info-box {
      background-color: #454d55 !important;
      color: #dee2e6 !important;
    }
    body.dark-mode .info-box .info-box-icon {
      background-color: #343a40 !important;
    }
    body.dark-mode .nav-sidebar .nav-link.active {
      background-color: #007bff !important; /* AdminLTE primary color */
      color: #fff !important;
    }
    body.dark-mode .control-sidebar {
      background-color: #343a40 !important;
      color: #dee2e6 !important;
    }
    body.dark-mode .control-sidebar::before {
      background-color: #343a40 !important;
    }
    .callscript-column {
      max-width: 500px; /* Adjusted for better visibility */
      white-space: normal;
      word-wrap: break-word;
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
<div class="wrapper">

  <!-- Navbar -->
  <nav class="main-header navbar navbar-expand navbar-white navbar-light">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="index.php" class="nav-link">Home</a>
      </li>
      <li class="nav-item d-none d-sm-inline-block">
        <a href="#" class="nav-link">Contact</a>
      </li>
    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ml-auto">
      <!-- Navbar Search -->
      <li class="nav-item">
        <a class="nav-link" data-widget="navbar-search" href="#" role="button">
          <i class="fas fa-search"></i>
        </a>
        <div class="navbar-search-block">
          <form class="form-inline">
            <div class="input-group input-group-sm">
              <input class="form-control form-control-navbar" type="search" placeholder="Search" aria-label="Search">
              <div class="input-group-append">
                <button class="btn btn-navbar" type="submit">
                  <i class="fas fa-search"></i>
                </button>
                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </div>
          </form>
        </div>
      </li>

      <!-- Notifications Dropdown Menu -->
      <li class="nav-item dropdown">
        <a class="nav-link" data-toggle="dropdown" href="#">
          <i class="far fa-bell"></i>
          <span class="badge badge-warning navbar-badge">15</span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <span class="dropdown-item dropdown-header">15 Notifications</span>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-envelope mr-2"></i> 4 new messages
            <span class="float-right text-muted text-sm">3 mins</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-users mr-2"></i> 8 friend requests
            <span class="float-right text-muted text-sm">12 hours</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item">
            <i class="fas fa-file mr-2"></i> 3 new reports
            <span class="float-right text-muted text-sm">2 days</span>
          </a>
          <div class="dropdown-divider"></div>
          <a href="#" class="dropdown-item text-center">See All Notifications</a>
        </div>
      </li>
      <!-- User Account: style can be found in dropdown.less -->
      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">

          <span class="d-none d-md-inline"></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
          <!-- User image -->
          <li class="user-header bg-primary">

            <p>
              Web Developer
              <small>Member since Nov. 2012</small>
            </p>
          </li>
          <!-- Menu Body -->
          <li class="user-body">
            <div class="row">
              <div class="col-4 text-center">
                <a href="#">Followers</a>
              </div>
              <div class="col-4 text-center">
                <a href="#">Sales</a>
              </div>
              <div class="col-4 text-center">
                <a href="#">Friends</a>
              </div>
            </div>
            <!-- /.row -->
          </li>
          <!-- Menu Footer-->
          <li class="user-footer">
            <a href="#" class="btn btn-default btn-flat">Profile</a>
            <a href="#" class="btn btn-default btn-flat float-right">Sign out</a>
          </li>
        </ul>
      </li>
      <li class="nav-item">
        <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button">
          <i class="fas fa-th-large"></i>
        </a>
      </li>
    </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="index.php" class="brand-link">

      <span class="brand-text font-weight-light">AI Sales</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
      <!-- Sidebar user panel (optional) -->


      <!-- SidebarSearch Form -->
      <div class="form-inline">
        <div class="input-group" data-widget="sidebar-search">
          <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
          <div class="input-group-append">
            <button class="btn btn-sidebar">
              <i class="fas fa-search fa-fw"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <!-- Add icons to the links using the .nav-icon class
               with font-awesome or any other icon font library -->
          <li class="nav-item menu-open">
            <a href="#" class="nav-link active">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard
                <i class="right fas fa-angle-left"></i>
              </p>
            </a>
           
        </ul>
      </nav>
      <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
  </aside>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1 class="m-0">Lead Details</h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="leads_list.php">Leads Overview</a></li>
              <li class="breadcrumb-item active">Lead Details</li>
            </ol>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Lead Information</h3>
              </div>
              <div class="card-body">
                <?php if ($lead): ?>
                  <div class="row">
                    <div class="col-md-4">
                      <p><strong>First Name:</strong> <?php echo htmlspecialchars((string)($lead['first_name'] ?? '')); ?></p>
                      <p><strong>Last Name:</strong> <?php echo htmlspecialchars((string)($lead['last_name'] ?? '')); ?></p>
                      <p><strong>Title:</strong> <?php echo htmlspecialchars((string)($lead['title'] ?? '')); ?></p>
                    </div>
                    <div class="col-md-4">
                      <p><strong>Phone Number:</strong> <?php echo htmlspecialchars((string)($lead['phone_number'] ?? '')); ?></p>
                      <p><strong>Email:</strong> <?php echo htmlspecialchars((string)($lead['email'] ?? '')); ?></p>
                      <p><strong>Industry:</strong> <?php echo htmlspecialchars((string)($lead['industry'] ?? '')); ?></p>
                    </div>
                    <div class="col-md-4">
                      <p><strong>Country:</strong> <?php echo htmlspecialchars((string)($lead['country'] ?? '')); ?></p>
                      <p><strong>State:</strong> <?php echo htmlspecialchars((string)($lead['company_state'] ?? '')); ?></p>
                    </div>
                  </div>
                  <button class="call-button btn btn-success mt-3" data-phone-number="<?php echo htmlspecialchars((string)($lead['phone_number'] ?? '')); ?>" data-leadsid="<?php echo htmlspecialchars((string)($lead['leadsid'] ?? '')); ?>" <?php if (empty($lead['phone_number'])) echo 'disabled'; ?>>Call Lead</button>
                <?php else: ?>
                  <p>Lead not found.</p>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-md-12">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Call History</h3>
              </div>
              <div class="card-body">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Call ID</th>
                      <th class="callscript-column">Call Script</th>
                      <th>Status</th>
                      <th>Timestamp</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($calls)): ?>
                      <?php foreach ($calls as $call): ?>
                        <tr>
                          <td><?php echo htmlspecialchars((string)($call['callid'])); ?></td>
                          <td class="callscript-column"><?php echo htmlspecialchars((string)($call['callscript'] ?? 'N/A')); ?></td>
                          <td><?php echo htmlspecialchars((string)($call['status'])); ?></td>
                          <td><?php
                                $timestamp = $call['timestamp'] ?? 'N/A';
                                if ($timestamp instanceof DateTime) {
                                    echo htmlspecialchars($timestamp->format('Y-m-d H:i:s'));
                                } else {
                                    echo htmlspecialchars((string)$timestamp);
                                }
                            ?></td>
                          <td>
                            <a href="call_details_view.php?callid=<?php echo htmlspecialchars((string)($call['callid'])); ?>" class="btn btn-primary btn-sm">Call log</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5">No calls found for this lead.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>




      </div><!--/. container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3">
      <h5>Title</h5>
      <p>Sidebar content</p>
      <hr class="mb-2">
      <h6>Dark Mode Toggle</h6>
      <div class="custom-control custom-switch">
        <input type="checkbox" class="custom-control-input" id="darkModeToggle">
        <label class="custom-control-label" for="darkModeToggle">Enable Dark Mode</label>
      </div>
    </div>
  </aside>
  <!-- /.control-sidebar -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2014-2021 <a href="#">cis</a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 3.2.0
    </div>
  </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<!-- overlayScrollbars -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/overlayscrollbars/1.13.1/js/jquery.overlayScrollbars.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>

<script>
  $(function () {
    // Dark Mode Toggle
    $('#darkModeToggle').on('change', function() {
      if ($(this).is(':checked')) {
        $('body').addClass('dark-mode');
        $('body').removeClass('light-mode'); // Ensure light-mode is removed if present
        $('.main-header').removeClass('navbar-white navbar-light').addClass('navbar-dark navbar-gray-dark');
        $('.main-sidebar').removeClass('sidebar-dark-primary').addClass('sidebar-light-primary'); // Example: change sidebar theme
      } else {
        $('body').removeClass('dark-mode');
        $('body').addClass('light-mode');
        $('.main-header').removeClass('navbar-dark navbar-gray-dark').addClass('navbar-white navbar-light');
        $('.main-sidebar').removeClass('sidebar-light-primary').addClass('sidebar-dark-primary');
      }
    });

    // Call button functionality
    $('.call-button').on('click', function () {
        const leadsid = $(this).data('leadsid');
        const phoneNumber = $(this).data('phoneNumber'); // Corrected data attribute
        console.log('Attempting to call via Node.js backend for Leadsid:', leadsid);

        if (!leadsid) {
            alert('Error: Lead ID is missing.');
            return;
        }

        // URL for the Node.js API endpoint
        const apiUrl = 'http://localhost:8081/api/make-call';

        try {
            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ leadId: leadsid }),
            })
            .then(response => {
                if (!response.ok) {
                    // If the server response is not OK, parse the error and throw it
                    return response.json().then(err => { throw new Error(err.error || 'Unknown server error') });
                }
                return response.json();
            })
            .then(data => {
                console.log('Received data from Node.js backend:', data);
                alert(data.message || 'Call initiated successfully!');
            })
            .catch(error => {
                console.error('Error during fetch operation:', error);
                alert('Error initiating call: ' + error.message);
            });
        } catch (error) {
            console.error('A synchronous error occurred:', error);
            alert('An unexpected error occurred: ' + error.message);
        }
    });
  });
</script>
</body>
</html>