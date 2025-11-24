<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/log/error.log'); // Ensure error logging is enabled

require __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php'; // Ensure Database class is loaded

$call_details = [];
$callid = null;
$error_message = null;

if (isset($_GET['callid']) && !empty($_GET['callid'])) {
    $callid = $_GET['callid'];

    try {
        $db = Database::getInstance();

        $stmt = $db->query('SELECT * FROM call_details WHERE callid = ? ORDER BY timestamp ASC', [$callid]);
        $call_details = $db->fetchAll($stmt);

    } catch (Exception $e) {
        error_log('Database error in call_details_view.php: ' . $e->getMessage());
        $error_message = 'Error fetching call details: ' . $e->getMessage();
    }
} else {
    $error_message = "No Call ID provided. Please provide a valid callid in the URL.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Call Details</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini">
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
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item">
            <a href="leads_list.php" class="nav-link">
              <i class="nav-icon fas fa-users"></i>
              <p>Leads</p>
            </a>
          </li>
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
            <h1 class="m-0">Call Details for Call ID: <?php echo htmlspecialchars($callid ?? 'N/A'); ?></h1>
          </div><!-- /.col -->
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="leads_list.php">Leads</a></li>
              <li class="breadcrumb-item active">Call Details</li>
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
                <h3 class="card-title">Conversation</h3>
              </div>
              <div class="card-body">
                <?php if ($error_message): ?>
                  <div class="alert alert-danger">
                    <?php echo $error_message; ?>
                  </div>
                <?php else: ?>
                <table class="table table-bordered">
                  <thead>
                    <tr>

                      <th>User Response</th>
                      <th>AI Response</th>
                      <th>Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($call_details)): ?>
                      <?php foreach ($call_details as $detail): ?>
                        <tr>

                          <td><?php echo htmlspecialchars($detail['user_response'] ?? 'N/A'); ?></td>
                          <td><?php echo htmlspecialchars($detail['ai_response'] ?? 'N/A'); ?></td>
                          <td><?php
                                $timestamp = $detail['timestamp'] ?? 'N/A';
                                if ($timestamp instanceof DateTime) {
                                    echo htmlspecialchars($timestamp->format('Y-m-d H:i:s'));
                                } else {
                                    echo htmlspecialchars($timestamp);
                                }
                            ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="3">No details found for this call.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div><!--/. container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

  <!-- Main Footer -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2014-2021 <a href="#">cis</a>.</strong>
    All rights reserved.
  </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>
</body>
</html>
