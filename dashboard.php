<?php
session_start();
include 'config.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $username = $user ? htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') : 'User';
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $username = 'User';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Task Management Web Application</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" integrity="sha256-Z4/0N6TL66XsK17O2qmx2W/rN3+FrD3A5Ur3xB8p8xI=" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous">
  <style>
    body { background-color: #fff; }
    .navbar { box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1); }
    .container-main { margin-top: 20px; }
    .fc-dayGridMonth-button, .fc-timeGridWeek-button, .fc-timeGridDay-button {
      background-color: #f0f0f0 !important;
      border: 2px solid black !important;
      color: black !important;
      margin-right: 5px;
    }
    .filter-type-container { display: flex; flex-direction: column; gap: 5px; }
    .filter-type-container .form-check-label {
      padding: 5px; border-radius: 4px; cursor: pointer; border: 1px solid transparent;
    }
    .filter-type-container .form-check-input:checked + .form-check-label {
      border: 1px solid black;
    }
    .filter-type-personal-label { background-color: #d1e7dd; }
    .filter-type-work-label { background-color: #ffe5d9; }
    .filter-type-urgent-label { background-color: #ffd1dc; }
    .filter-type-custom-label { background-color: #cce5ff; }
    #leftListTable { width: 100%; }
    #leftListTable td { border-top: none; padding: 0.5rem; }
    .type-badge {
      display: inline-block; min-width: 50px; text-align: center; border-radius: 4px;
      color: black; font-size: 0.8rem; margin-left: 10px; padding: 3px 5px;
    }
    .task-type-personal { background-color: #d1e7dd; }
    .task-type-work { background-color: #ffe5d9; }
    .task-type-urgent { background-color: #ffd1dc; }
    .task-type-custom { background-color: #cce5ff; }
    #summaryTable tr.task-row { transition: background-color 0.3s; }
    #summaryTable tr.task-row:hover { background-color: #f1f1f1; }
    #leftListTable tr { cursor: pointer; }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
      <a class="navbar-brand" href="#">Calendar</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <button class="btn btn-outline-primary me-2" id="createEventButton">
          <i class="fas fa-plus"></i> Create
        </button>
        <ul class="navbar-nav">
          <li class="nav-item"><span class="nav-link">Welcome, <?php echo $username; ?></span></li>
          <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
  <div class="container container-main">
    <div class="row">
      <div class="col-md-3">
        <div class="card mb-3">
          <div class="card-header"><h5>Filter by Type</h5></div>
          <div class="card-body">
            <div class="filter-type-container">
              <div class="form-check">
                <input class="form-check-input filter-type-checkbox" type="checkbox" value="personal" id="filterPersonal">
                <label class="form-check-label filter-type-personal-label" for="filterPersonal">Personal</label>
              </div>
              <div class="form-check">
                <input class="form-check-input filter-type-checkbox" type="checkbox" value="work" id="filterWork">
                <label class="form-check-label filter-type-work-label" for="filterWork">Work</label>
              </div>
              <div class="form-check">
                <input class="form-check-input filter-type-checkbox" type="checkbox" value="urgent" id="filterUrgent">
                <label class="form-check-label filter-type-urgent-label" for="filterUrgent">Urgent</label>
              </div>
              <div id="customFilterContainer"></div>
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h5>Task List</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <input type="text" class="form-control" id="taskSearchInput" placeholder="Search tasks">
            </div>
            <table class="table table-borderless" id="leftListTable">
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="col-md-9">
        <div id="calendar"></div>
      </div>
    </div>
  </div>
  <div id="summaryContainer" class="container mt-4">
    <div class="card">
      <div class="card-header">
        <h5>Task Summary</h5>
        <div id="filterInfo" class="text-muted"></div>
      </div>
      <div class="card-body">
        <table class="table table-bordered table-hover">
          <thead>
            <tr>
              <th>Alert</th>
              <th>Title</th>
              <th>Description</th>
              <th>Start</th>
              <th>End</th>
              <th>Task Type</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="summaryTable"></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="eventForm">
          <div class="modal-header">
            <h5 class="modal-title" id="eventModalTitle">Create Event</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="taskId" value="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
              <label class="form-label">Title</label>
              <input type="text" id="eventTitle" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea id="eventDescription" class="form-control"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Task Type</label>
              <select id="eventTaskType" class="form-select" required></select>
            </div>
            <div class="mb-3" id="customTaskTypeContainer" style="display:none;">
              <label class="form-label">Custom Task Tag</label>
              <input type="text" id="customTaskType" class="form-control" placeholder="Enter custom task tag">
            </div>
            <div id="customTaskColorContainer" style="display:none; margin-bottom: 1rem;">
              <label class="form-label" for="customTaskColor" style="display:inline-block; margin-right: 5px;">Color:</label>
              <input type="color" id="customTaskColor" class="form-control form-control-color" value="#cce5ff" title="Choose your color" style="width: 40px; height: 40px; padding: 0; border: none; display:inline-block;">
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="eventAllDay">
              <label class="form-check-label" for="eventAllDay">All Day Event</label>
            </div>
            <div class="mb-3">
              <label class="form-label">Recurring</label>
              <select id="eventRecurring" class="form-select">
                <option value="none">None</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
            </div>
            <div class="mb-3" id="recurringEndContainer" style="display:none;">
              <label class="form-label">Recurring End Date</label>
              <input type="date" id="eventRecurringEnd" class="form-control">
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" id="eventStartDate" class="form-control" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Start Time</label>
                <input type="time" id="eventStartTime" class="form-control" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">End Date</label>
                <input type="date" id="eventEndDate" class="form-control">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">End Time</label>
                <input type="time" id="eventEndTime" class="form-control">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveButton">Save Event</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
         <div class="modal-header">
           <h5 class="modal-title" id="detailModalLabel">Task Details</h5>
           <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
         </div>
         <div class="modal-body">
           <div id="detailContent"></div>
         </div>
         <div class="modal-footer">
           <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
         </div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" integrity="sha256-Z4/0N6TL66XsK17O2qmx2W/rN3+FrD3A5Ur3xB8p8xI=" crossorigin="anonymous"></script>
  <script>
var filterTypes = [];
var csrfToken = "<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>";
$(document).ready(function() {
  function updateCustomTaskTypesDropdown() {
    var dropdown = $("#eventTaskType");
    var currentVal = dropdown.val();
    dropdown.empty();
    var baseOptions = [
      { value: "personal", text: "Personal" },
      { value: "work", text: "Work" },
      { value: "urgent", text: "Urgent" }
    ];
    baseOptions.forEach(function(opt) {
      dropdown.append($("<option></option>").attr("value", opt.value).text(opt.text));
    });
    $.ajax({
      url: "tasks.php?action=fetch",
      method: "GET",
      dataType: "json",
      success: function(data) {
        var customTypes = [];
        data.forEach(function(task) {
          if(["personal", "work", "urgent"].indexOf(task.task_type) === -1 &&
             customTypes.indexOf(task.task_type) === -1) {
            customTypes.push(task.task_type);
          }
        });
        customTypes.sort();
        customTypes.forEach(function(type) {
          dropdown.append($("<option></option>").attr("value", type).text(type));
        });
        dropdown.append($("<option></option>").attr("value", "custom").text("New Custom"));
        if(dropdown.find("option[value='"+currentVal+"']").length > 0) {
          dropdown.val(currentVal);
          if(currentVal === "custom") {
            $("#customTaskTypeContainer, #customTaskColorContainer").show();
          } else {
            $("#customTaskTypeContainer, #customTaskColorContainer").hide();
          }
        } else {
          dropdown.val("personal");
          $("#customTaskTypeContainer, #customTaskColorContainer").hide();
        }
      },
      error: function(xhr, status, error) {
        console.error("Error fetching tasks for custom types:", error);
        showAlert("Failed to load task types.", "danger");
      }
    });
  }
  $("#eventRecurring").on("change", function() {
    if($(this).val() === "none") {
      $("#recurringEndContainer").hide();
    } else {
      $("#recurringEndContainer").show();
    }
  });
  var calendarEl = document.getElementById('calendar');
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: "timeGridWeek",
    editable: true,
    headerToolbar: {
      left: "prev,next today",
      center: "title",
      right: "dayGridMonth,timeGridWeek,timeGridDay"
    },
    events: function(fetchInfo, successCallback) {
      $.ajax({
        url: "tasks.php?action=fetch",
        method: "GET",
        dataType: "json",
        success: function(data) {
          var filtered = data.filter(function(task) {
            var typeMatches = (filterTypes.length === 0 || filterTypes.indexOf(task.task_type) !== -1);
            return typeMatches && task.show_in_calendar;
          });
          var events = [];
          var fetchStart = fetchInfo.start;
          var fetchEnd = fetchInfo.end;
          filtered.forEach(function(task) {
            var originalStart = new Date(task.start);
            var duration = task.end ? new Date(task.end) - new Date(task.start) : 0;
            if(task.recurring && task.recurring !== "none") {
              var occDate = new Date(originalStart);
              var recEnd = task.recurringEnd ? new Date(task.recurringEnd) : null;
              while(occDate < fetchStart) {
                if(task.recurring === "weekly"){
                  occDate.setDate(occDate.getDate() + 7);
                } else if(task.recurring === "daily"){
                  occDate.setDate(occDate.getDate() + 1);
                } else if(task.recurring === "monthly"){
                  occDate.setMonth(occDate.getMonth() + 1);
                }
              }
              while(occDate < fetchEnd) {
                if(recEnd && occDate > recEnd) { break; }
                var eventObj = {
                  id: task.task_id,
                  title: task.title,
                  start: occDate.toISOString(),
                  end: duration > 0 ? new Date(occDate.getTime() + duration).toISOString() : "",
                  allDay: task.allDay,
                  className: (["personal", "work", "urgent"].indexOf(task.task_type) !== -1) ?
                              ("task-type-" + task.task_type) : "task-type-custom",
                  extendedProps: {
                    description: task.description,
                    task_type: task.task_type,
                    status: task.status,
                    recurring: task.recurring,
                    recurringEnd: task.recurringEnd
                  }
                };
                if(["personal", "work", "urgent"].indexOf(task.task_type) === -1 && task.customColor) {
                  eventObj.backgroundColor = task.customColor;
                  eventObj.borderColor = task.customColor;
                }
                events.push(eventObj);
                if(task.recurring === "weekly"){
                  occDate.setDate(occDate.getDate() + 7);
                } else if(task.recurring === "daily"){
                  occDate.setDate(occDate.getDate() + 1);
                } else if(task.recurring === "monthly"){
                  occDate.setMonth(occDate.getMonth() + 1);
                }
              }
            } else {
              var eventObj = {
                id: task.task_id,
                title: task.title,
                start: task.start,
                end: task.end,
                allDay: task.allDay,
                className: (["personal", "work", "urgent"].indexOf(task.task_type) !== -1) ?
                            ("task-type-" + task.task_type) : "task-type-custom",
                extendedProps: {
                  description: task.description,
                  task_type: task.task_type,
                  status: task.status,
                  recurring: task.recurring,
                  recurringEnd: task.recurringEnd
                }
              };
              if(["personal", "work", "urgent"].indexOf(task.task_type) === -1 && task.customColor) {
                eventObj.backgroundColor = task.customColor;
                eventObj.borderColor = task.customColor;
              }
              events.push(eventObj);
            }
          });
          successCallback(events);
        },
        error: function(xhr, status, error) {
          console.error("Error fetching calendar events:", error);
          showAlert("Failed to load calendar events.", "danger");
        }
      });
    },
    eventClick: function(info) {
      var detailHtml = "<h5>" + info.event.title + "</h5>";
      if(info.event.allDay){
        detailHtml += "<p><strong>Start:</strong> " + info.event.start.toLocaleDateString() + "</p>";
      } else {
        detailHtml += "<p><strong>Start:</strong> " + info.event.start.toLocaleString() + "</p>";
      }
      if(info.event.end){
        if(info.event.allDay){
          detailHtml += "<p><strong>End:</strong> " + new Date(info.event.end).toLocaleDateString() + "</p>";
        } else {
          detailHtml += "<p><strong>End:</strong> " + info.event.end.toLocaleString() + "</p>";
        }
      }
      if(info.event.extendedProps.description){
        detailHtml += "<p><strong>Description:</strong> " + info.event.extendedProps.description + "</p>";
      }
      detailHtml += "<p><strong>Type:</strong> " + info.event.extendedProps.task_type + "</p>";
      if(info.event.extendedProps.recurring && info.event.extendedProps.recurring !== "none"){
        var recurringText = "Every " + info.event.extendedProps.recurring.charAt(0).toUpperCase() + info.event.extendedProps.recurring.slice(1);
        if(info.event.extendedProps.recurringEnd){
          recurringText += " till " + new Date(info.event.extendedProps.recurringEnd).toLocaleDateString();
        }
        detailHtml += "<p><strong>Recurring:</strong> " + recurringText + "</p>";
      }
      $("#detailContent").html(detailHtml);
      $("#detailModal").modal("show");
    },
    eventDrop: function(info) {
      $.ajax({
        url: "tasks.php",
        method: "POST",
        data: {
          action: "update",
          task_id: info.event.id,
          title: info.event.title,
          description: info.event.extendedProps.description,
          start: info.event.start.toISOString(),
          end: info.event.end ? info.event.end.toISOString() : "",
          task_type: info.event.extendedProps.task_type,
          customColor: info.event.backgroundColor || "",
          recurring: info.event.extendedProps.recurring,
          recurringEnd: info.event.extendedProps.recurringEnd,
          allDay: info.event.allDay ? 1 : 0,
          csrf_token: csrfToken
        },
        dataType: "json",
        success: function(response) {
          showAlert("Event updated via drag & drop!", "success");
          loadSummary();
          loadLeftList();
        },
        error: function(xhr, status, error) {
          console.error("Error updating event after drag & drop:", error);
          showAlert("Failed to update event.", "danger");
        }
      });
    }
  });
  calendar.render();
  $(document).on("change", ".filter-type-checkbox", function() {
    filterTypes = [];
    $(".filter-type-checkbox:checked").each(function() {
      filterTypes.push($(this).val());
    });
    calendar.refetchEvents();
    loadSummary();
    loadLeftList();
  });
  $("#taskSearchInput").on("keyup", function() {
    loadLeftList();
  });
  $("#eventTaskType").on("change", function(){
    if($(this).val() === "custom"){
      $("#customTaskTypeContainer, #customTaskColorContainer").show();
    } else {
      $("#customTaskTypeContainer, #customTaskColorContainer").hide();
      $("#customTaskType").val("");
    }
  });
  function updateCustomFilters() {
    $.ajax({
      url: "tasks.php?action=fetch",
      method: "GET",
      dataType: "json",
      success: function(data) {
        var customTypes = {};
        data.forEach(function(task) {
          if(["personal", "work", "urgent"].indexOf(task.task_type) === -1) {
            if(!customTypes[task.task_type]) {
              customTypes[task.task_type] = task.customColor || "#cce5ff";
            }
          }
        });
        var customFilterContainer = $("#customFilterContainer");
        customFilterContainer.empty();
        for(var type in customTypes) {
           var id = "filterCustom_" + type.replace(/\s+/g, '');
           var bgColor = customTypes[type];
           var checkboxHtml = '<div class="form-check">' +
               '<input class="form-check-input filter-type-checkbox" type="checkbox" value="'+ type +'" id="'+ id +'">' +
               '<label class="form-check-label filter-type-custom-label" for="'+ id +'" style="background-color:' + bgColor + '; padding: 5px; border-radius: 4px; cursor: pointer;">' + type + '</label>' +
               '</div>';
           customFilterContainer.append(checkboxHtml);
        }
      },
      error: function(xhr, status, error) {
        console.error("Error loading custom filters:", error);
        showAlert("Failed to load filters.", "danger");
      }
    });
  }
  function loadSummary() {
    $.ajax({
      url: "tasks.php?action=fetch",
      method: "GET",
      dataType: "json",
      success: function(data) {
        var filtered = data.filter(function(task) {
          var typeMatches = (filterTypes.length === 0 || filterTypes.indexOf(task.task_type) !== -1);
          return typeMatches && task.show_in_list;
        });
        filtered.sort(function(a, b) { return new Date(a.start) - new Date(b.start); });
        var summaryHtml = "";
        var today = new Date(); today.setHours(0,0,0,0);
        var tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
        filtered.forEach(function(task) {
          var eventDate = new Date(task.start);
          var startStr = "";
          var endStr = "";
          if(task.allDay){
            startStr = eventDate.toLocaleDateString();
            endStr = task.end ? new Date(task.end).toLocaleDateString() : "";
          } else {
            startStr = eventDate.toLocaleString();
            endStr = task.end ? new Date(task.end).toLocaleString() : "";
          }
          var alertIcon = "";
          var eventDay = new Date(eventDate.getFullYear(), eventDate.getMonth(), eventDate.getDate());
          if(eventDay.getTime() === today.getTime() || eventDay.getTime() === tomorrow.getTime()){
            alertIcon = '<i class="fas fa-bell text-danger"></i>';
          }
          var actions = "";
          actions += '<button class="btn btn-info btn-sm edit-task" data-id="'+ task.task_id +'"><i class="fas fa-edit"></i> Edit</button> ';
          if(task.status !== "completed") {
            actions += '<button class="btn btn-success btn-sm complete-task" data-id="'+ task.task_id +'"><i class="fas fa-check"></i> Complete</button> ';
          } else {
            actions += '<span class="badge bg-secondary toggle-status" data-id="'+ task.task_id +'" style="cursor:pointer;">Completed</span> ';
          }
          actions += '<button class="btn btn-danger btn-sm delete-task" data-id="'+ task.task_id +'"><i class="fas fa-trash"></i></button>';
          var typeDisplay = task.task_type.charAt(0).toUpperCase() + task.task_type.slice(1);
          if(task.recurring && task.recurring !== "none"){
            var recurringText = "Every " + task.recurring.charAt(0).toUpperCase() + task.recurring.slice(1);
            if(task.recurringEnd){
              recurringText += " till " + new Date(task.recurringEnd).toLocaleDateString();
            }
            typeDisplay += "<br><small>" + recurringText + "</small>";
          }
          summaryHtml += '<tr class="task-row">';
          summaryHtml += '<td>'+ alertIcon +'</td>';
          summaryHtml += '<td>'+ task.title +'</td>';
          summaryHtml += '<td>'+(task.description || "") +'</td>';
          summaryHtml += '<td>'+ startStr +'</td>';
          summaryHtml += '<td>'+ endStr +'</td>';
          summaryHtml += '<td>'+ typeDisplay +'</td>';
          summaryHtml += '<td>'+ actions +'</td>';
          summaryHtml += '</tr>';
        });
        $("#summaryTable").html(summaryHtml);
        var infoText = "Showing " + (filterTypes.length > 0 ? "Type Filter: " + filterTypes.join(", ") : "All Tasks");
        $("#filterInfo").text(infoText);
        updateCustomFilters();
      },
      error: function(xhr, status, error) {
        console.error("Error loading summary:", error);
        showAlert("Failed to load task summary.", "danger");
      }
    });
  }
  function loadLeftList() {
    $.ajax({
      url: "tasks.php?action=fetch",
      method: "GET",
      dataType: "json",
      success: function(data) {
        var filtered = data.filter(function(task) {
          var typeMatches = (filterTypes.length === 0 || filterTypes.indexOf(task.task_type) === -1);
          return typeMatches && task.show_in_list;
        });
        var searchQuery = $("#taskSearchInput").val().toLowerCase();
        if(searchQuery) {
          filtered = filtered.filter(function(task){
             return task.title.toLowerCase().includes(searchQuery);
          });
        }
        filtered.sort(function(a, b) { return new Date(a.start) - new Date(b.start); });
        filtered.sort(function(a, b) { return new Date(a.start) - new Date(b.start); });
        var listHtml = "";
        filtered.forEach(function(task) {
          var dateObj = new Date(task.start);
          var weekday = dateObj.toLocaleDateString("en-US", { weekday: "long" });
          var dateStr = dateObj.toLocaleDateString("en-US", { year:"numeric", month:"2-digit", day:"2-digit" });
          var typeCap = task.task_type.charAt(0).toUpperCase() + task.task_type.slice(1);
          var badgeClass = (["personal","work","urgent"].indexOf(task.task_type) !== -1) ?
              ("task-type-" + task.task_type) : "task-type-custom";
          var styleAttr = "";
          if(["personal","work","urgent"].indexOf(task.task_type) === -1 && task.customColor) {
            styleAttr = ' style="background-color: ' + task.customColor + ';"';
          }
          listHtml += '<tr data-task-id="' + task.task_id + '">';
          listHtml += '<td>';
          listHtml += '<div>';
          listHtml += '<span class="fw-bold">' + weekday + " " + dateStr + '</span>';
          listHtml += '<span class="type-badge ' + badgeClass + '"' + styleAttr + '>' + typeCap + '</span>';
          listHtml += '</div>';
          listHtml += '<div>' + task.title + '</div>';
          listHtml += '</td>';
          listHtml += '</tr>';
        });
        $("#leftListTable tbody").html(listHtml);
      },
      error: function(xhr, status, error) {
        console.error("Error loading left list:", error);
        showAlert("Failed to load task list.", "danger");
      }
    });
  }
  loadSummary();
  loadLeftList();
  $("#createEventButton").on("click", function() {
    $("#eventForm")[0].reset();
    $("#taskId").val("");
    $("#eventModalTitle").text("Create Event");
    $("#saveButton").text("Save Event");
    $("#customTaskTypeContainer, #customTaskColorContainer").hide();
    $("#customTaskType").val("");
    updateCustomTaskTypesDropdown();
    $("#eventRecurring").val("none");
    $("#recurringEndContainer").hide();
    $("#eventRecurringEnd").val("");
    $("#eventAllDay").prop("checked", false);
    $("#eventStartTime, #eventEndTime").prop("disabled", false);
    $("#eventModal").modal("show");
  });
  $(document).on("click", ".edit-task", function() {
    var taskId = $(this).data("id");
    $.ajax({
      url: "tasks.php?action=fetch",
      method: "GET",
      dataType: "json",
      success: function(data) {
        var task = data.find(t => t.task_id == taskId);
        if(task) {
          $("#taskId").val(task.task_id);
          $("#eventTitle").val(task.title);
          $("#eventDescription").val(task.description);
          if(["personal", "work", "urgent"].indexOf(task.task_type) === -1) {
             $("#eventTaskType").val("custom");
             $("#customTaskTypeContainer, #customTaskColorContainer").show();
             $("#customTaskType").val(task.task_type);
             $("#customTaskColor").val(task.customColor || "#cce5ff");
          } else {
             $("#eventTaskType").val(task.task_type);
             $("#customTaskTypeContainer, #customTaskColorContainer").hide();
             $("#customTaskType").val("");
          }
          $("#eventRecurring").val(task.recurring);
          $("#eventRecurringEnd").val(task.recurringEnd);
          if(task.recurring === "none") {
              $("#recurringEndContainer").hide();
          } else {
              $("#recurringEndContainer").show();
          }
          if(task.allDay) {
             $("#eventAllDay").prop("checked", true);
             $("#eventStartTime, #eventEndTime").prop("disabled", true);
          } else {
             $("#eventAllDay").prop("checked", false);
             $("#eventStartTime, #eventEndTime").prop("disabled", false);
          }
          var startDateTime = new Date(task.start);
          var endDateTime = task.end ? new Date(task.end) : null;
          $("#eventStartDate").val(startDateTime.toISOString().split("T")[0]);
          if(task.allDay){
             $("#eventStartTime").val("");
             $("#eventEndDate").val(endDateTime ? endDateTime.toISOString().split("T")[0] : "");
             $("#eventEndTime").val("");
          } else {
             $("#eventStartTime").val(startDateTime.toTimeString().slice(0, 5));
             if(endDateTime) {
                $("#eventEndDate").val(endDateTime.toISOString().split("T")[0]);
                $("#eventEndTime").val(endDateTime.toTimeString().slice(0, 5));
             } else {
                $("#eventEndDate").val("");
                $("#eventEndTime").val("");
             }
          }
          $("#eventModalTitle").text("Edit Event");
          $("#saveButton").text("Update Event");
          $("#eventModal").modal("show");
        }
      },
      error: function(xhr, status, error) {
        console.error("Error fetching task for edit:", error);
        showAlert("Failed to load task for editing.", "danger");
      }
    });
  });
  $("#eventForm").on("submit", function(e) {
    e.preventDefault();
    var isAllDay = $("#eventAllDay").is(":checked") ? 1 : 0;
    var startDate = $("#eventStartDate").val();
    var startTime = $("#eventStartTime").val();
    var endDate = $("#eventEndDate").val();
    var endTime = $("#eventEndTime").val();
    var startDateTime, endDateTime;
    if(isAllDay) {
      startDateTime = startDate;
      endDateTime = endDate;
    } else {
      startDateTime = startDate + "T" + startTime;
      endDateTime = (endDate && endTime) ? (endDate + "T" + endTime) : null;
    }
    var selectedTaskType = $("#eventTaskType").val();
    var customColor = "";
    if(selectedTaskType === "custom") {
      var customVal = $("#customTaskType").val().trim();
      if(customVal) { selectedTaskType = customVal; }
      customColor = $("#customTaskColor").val();
    }
    var taskId = $("#taskId").val();
    var actionType = taskId ? "update" : "add";
    var formData = {
      action: actionType,
      title: $("#eventTitle").val(),
      description: $("#eventDescription").val(),
      start: startDateTime,
      end: endDateTime,
      task_type: selectedTaskType,
      customColor: customColor,
      recurring: $("#eventRecurring").val(),
      recurringEnd: $("#eventRecurringEnd").val(),
      allDay: isAllDay,
      csrf_token: csrfToken
    };
    if(taskId) { formData.task_id = taskId; }
    $.ajax({
      url: "tasks.php",
      method: "POST",
      data: formData,
      dataType: "json",
      success: function(response) {
        calendar.refetchEvents();
        loadSummary();
        loadLeftList();
        $("#eventModal").modal("hide");
        showAlert(response.message || (actionType === "add" ? "Event added successfully!" : "Event updated successfully!"), "success");
      },
      error: function(xhr, status, error) {
        console.error("Error processing event:", error);
        showAlert("Failed to process event.", "danger");
      }
    });
  });
  $(document).on("click", ".complete-task", function() {
    var taskId = $(this).data("id");
    $.ajax({
      url: "tasks.php",
      method: "POST",
      data: { action: "complete", task_id: taskId, csrf_token: csrfToken },
      dataType: "json",
      success: function(response) {
        calendar.refetchEvents();
        loadSummary();
        loadLeftList();
        showAlert(response.message || "Task marked as completed!", "success");
      },
      error: function(xhr, status, error) {
        console.error("Error completing task:", error);
        showAlert("Failed to mark task as completed.", "danger");
      }
    });
  });
  $(document).on("click", ".toggle-status", function() {
    var taskId = $(this).data("id");
    $.ajax({
      url: "tasks.php",
      method: "POST",
      data: { action: "toggle", task_id: taskId, csrf_token: csrfToken },
      dataType: "json",
      success: function(response) {
        calendar.refetchEvents();
        loadSummary();
        loadLeftList();
        showAlert(response.message || "Task status toggled!", "success");
      },
      error: function(xhr, status, error) {
        console.error("Error toggling task status:", error);
        showAlert("Failed to toggle task status.", "danger");
      }
    });
  });
  $(document).on("click", ".delete-task", function() {
    if(confirm("Are you sure you want to delete this event?")) {
      var taskId = $(this).data("id");
      $.ajax({
        url: "tasks.php",
        method: "POST",
        data: { action: "delete", task_id: taskId, csrf_token: csrfToken },
        dataType: "json",
        success: function(response) {
          calendar.refetchEvents();
          loadSummary();
          loadLeftList();
          showAlert(response.message || "Event deleted successfully!", "success");
        },
        error: function(xhr, status, error) {
          console.error("Error deleting task:", error);
          showAlert("Failed to delete event.", "danger");
        }
      });
    }
  });
  $(document).on("click", "#leftListTable tr", function() {
    var taskId = $(this).data("task-id");
    $.ajax({
      url: "tasks.php?action=fetch",
      method: "GET",
      dataType: "json",
      success: function(data) {
        var task = data.find(t => t.task_id == taskId);
        if (task) {
          var detailHtml = "<h5>" + task.title + "</h5>";
          if(task.allDay){
            detailHtml += "<p><strong>Start:</strong> " + new Date(task.start).toLocaleDateString() + "</p>";
          } else {
            detailHtml += "<p><strong>Start:</strong> " + new Date(task.start).toLocaleString() + "</p>";
          }
          if(task.end) {
            if(task.allDay){
              detailHtml += "<p><strong>End:</strong> " + new Date(task.end).toLocaleDateString() + "</p>";
            } else {
              detailHtml += "<p><strong>End:</strong> " + new Date(task.end).toLocaleString() + "</p>";
            }
          }
          if(task.description) {
            detailHtml += "<p><strong>Description:</strong> " + task.description + "</p>";
          }
          detailHtml += "<p><strong>Type:</strong> " + task.task_type.charAt(0).toUpperCase() + task.task_type.slice(1) + "</p>";
          if(task.recurring && task.recurring !== "none"){
              var recurringText = "Every " + task.recurring.charAt(0).toUpperCase() + task.recurring.slice(1);
              if(task.recurringEnd){
                recurringText += " till " + new Date(task.recurringEnd).toLocaleDateString();
              }
              detailHtml += "<p><strong>Recurring:</strong> " + recurringText + "</p>";
          }
          $("#detailContent").html(detailHtml);
          $("#detailModal").modal("show");
        }
      },
      error: function(xhr, status, error) {
        console.error("Error fetching task details:", error);
        showAlert("Failed to load task details.", "danger");
      }
    });
  });
  function showAlert(message, type) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
      message +
      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    $("body").append(alertHtml);
    setTimeout(function() { $(".alert").alert("close"); }, 3000);
  }
  $("#eventAllDay").change(function(){
    if($(this).is(":checked")) {
      $("#eventStartTime, #eventEndTime").prop("disabled", true).val("");
    } else {
      $("#eventStartTime, #eventEndTime").prop("disabled", false);
    }
  });
  setInterval(function() {
    calendar.refetchEvents();
    loadSummary();
    loadLeftList();
  }, 10000);
});
  </script>
</body>
</html>
