var LXC_T = window.LXC_T || function (s) { return s; };
var LXC_TF = window.LXC_TF || function (s) { return s; };

//Don't execute commands again if page is refreshed
if ( window.history.replaceState ) {
  window.history.replaceState( null, null, window.location.href );
}

function postAction(name, id) {
  const validActions = [
    'stopCONT', 'restartCONT', 'freezeCONT', 'killCONT', 'unfreezeCONT',
    'startCONT', 'startALLCONT', 'stopALLCONT', 'freezeALLCONT', 'unfreezeALLCONT',
    'destroyCONT', 'snapshotCONT', 'backupCONT', 'deleteSNAP', 'deleteBACKUP'
  ];
  let action = name;
  if (typeof name === 'string') {
    const classes = name.split(/\s+/);
    const matched = validActions.find(function(act) { return classes.indexOf(act) !== -1; });
    if (matched) {
      action = matched;
    } else {
      action = classes[0];
    }
  }
  let postData = {
    'lxc'      : '',
    'action'   : action,
    'container': id
  };
  $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
    parent.window.location.reload();
  });
}

function saveConfig(name) {
  const isSwal2 = (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') ||
                  (typeof swal !== 'undefined' && typeof swal.fire === 'function');
  if (isSwal2) {
    const swalObj = (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') ? Swal : swal;
    swalObj.fire({
      title: LXC_T("Saving"),
      html: LXC_TF("Saving configuration for LXC container: <span style=\"font-weight:bold\">%s</span>, please wait...", name),
      allowEscapeKey: false,
      showConfirmButton: false,
      allowOutsideClick: false,
      didOpen: function() {
        if (typeof swalObj.showLoading === 'function') {
          swalObj.showLoading();
        }
      }
    });
  } else if (typeof swal === 'function') {
    swal({
      title: LXC_T("Saving"),
      text: LXC_TF("Saving configuration for LXC container: <span style=\"font-weight:bold\">%s</span>, please wait...", name),
      closeOnEsc: false,
      showConfirmButton: false,
      allowOutsideClick: false,
      html: true
    });
  }
  let updatedConfig = $('#configEditor').val();
  $.ajax({
    type: "POST",
    url: '/plugins/lxc/include/ajax.php',
    data: {
      'lxc': '',
      'action': 'saveConfig',
      'container': name,
      'updatedConfig': updatedConfig
    },
    success: function(response) {
      location.reload();
    },
  });
}

function startConsole(name, navigate) {
  openTerminal('lxc', name);
  if (navigate) {
    setTimeout(function() {
      top.Shadowbox.close();
      location.href = '/LXC';
    }, 1500);
  }
}

// Function to wait for an element to be available
function waitForElement(elementPath, callBack){
  window.setTimeout(function(){
    if($(elementPath).length){
      callBack(elementPath, $(elementPath));
    }else{
      waitForElement(elementPath, callBack);
    }
  },500)
}

// Function that shows the dialog
function showDialog(callback, text, title) {
  title = title || LXC_T("Proceed?");
  const isSwal2 = (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') ||
                  (typeof swal !== 'undefined' && typeof swal.fire === 'function');
  if (isSwal2) {
    const swalObj = (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') ? Swal : swal;
    swalObj.fire({
      title: title,
      html: text,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: LXC_T("Proceed"),
      cancelButtonText: LXC_T("Cancel")
    }).then(function(result) {
      if (typeof callback === 'function') {
        callback(result.isConfirmed || result.value === true);
      }
    });
  } else if (typeof swal === 'function') {
    var res = swal({
      title: title,
      text: text,
      type: 'warning',
      html: true,
      showCancelButton: true,
      confirmButtonText: LXC_T("Proceed"),
      cancelButtonText: LXC_T("Cancel")
    },
    function(confirm){
      if (typeof callback === 'function') {
        callback(confirm);
      }
    });
    if (res && typeof res.then === 'function') {
      res.then(function(result) {
        if (typeof callback === 'function') {
          callback(result.isConfirmed || result.value === true);
        }
      });
    }
  } else {
    var strippedText = (text || '').replace(/<[^>]*>?/gm, '');
    var confirmed = confirm(strippedText || title);
    if (typeof callback === 'function') {
      callback(confirmed);
    }
  }
}

function showPrompt(callback, title, text, placeholder) {
  const isSwal2 = (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') ||
                  (typeof swal !== 'undefined' && typeof swal.fire === 'function');
  if (isSwal2) {
    const swalObj = (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') ? Swal : swal;
    swalObj.fire({
      title: title,
      text: text,
      input: 'text',
      inputPlaceholder: placeholder,
      showCancelButton: true,
      confirmButtonText: LXC_T("OK"),
      cancelButtonText: LXC_T("Cancel")
    }).then(function(result) {
      if (typeof callback === 'function') {
        if (result.isConfirmed) {
          callback(result.value !== undefined ? result.value : "");
        } else {
          callback(false);
        }
      }
    });
  } else if (typeof swal === 'function') {
    var res = swal({
      title: title,
      text: text,
      type: "input",
      showCancelButton: true,
      closeOnConfirm: true,
      inputPlaceholder: placeholder
    },
    function(confirm) {
      if (typeof callback === 'function') {
        callback(confirm);
      }
    });
    if (res && typeof res.then === 'function') {
      res.then(function(result) {
        if (typeof callback === 'function') {
          if (result.isConfirmed) {
            callback(result.value !== undefined ? result.value : "");
          } else {
            callback(false);
          }
        }
      });
    }
  } else {
    var val = prompt((title ? title + "\n" : "") + text, "");
    if (typeof callback === 'function') {
      callback(val !== null ? val : false);
    }
  }
}

// Prevent closing shadowbox when editing config
document.addEventListener('keydown', function(event) {
  const activeElement = document.activeElement;
  if (activeElement && activeElement.tagName.toLowerCase() === "textarea") {
    event.stopPropagation();
    return;
  }
}, true);



// Function that shows the status dialog
function showStatus(action, id, title, text, extra) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: title,
    onClose: function () {
      location.reload();
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: $.extend({
        'lxc': '',
        'action': action,
        'container': id
      }, extra || {}),
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>" + LXC_TF("%s %s, please wait until the DONE button is displayed!", title, id) + "</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("<p>" + LXC_TF("%s %s, please wait until the DONE button is displayed!", title, id) + "</p>");
        statusInterval = setInterval(function () {
          dialogContent.append(".");
        }, 5000);
      },
      success: function (data) {
    if (data.indexOf("lxc-error") === -1) {
          dialogContent.append("<p/>");
    }
        dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">' + LXC_T("Done") + '</button></p>');
        clearInterval(statusInterval);
      }
    });
  });
}


// The menu is position: fixed so the scroll container around the container
// table cannot clip it (see the .lxc-dropdown rules in css/lxc.css). That means
// it has to be placed by hand: at the pointer, then slid back inside the
// viewport when the bottom or the right edge is short.
//
// The menu opens where the click landed, not at the icon's box: the icon is a
// 30px square and aligning to its corner leaves the menu visibly offset from
// the cursor. What is cached is the offset from the clicked trigger's
// top-left corner to the click point, so a scroll or resize can re-measure the
// trigger and put the menu back at the same place relative to its row.
var lxcOpenMenu = null;
var lxcOpenTrigger = null;
var lxcOpenOffset = null;

// Pending open timer. The open is deferred so the WebGUI's own document-level
// dropdown handling, which runs while this click bubbles, has finished before
// the menu is shown; showing it synchronously makes the WebGUI close it again
// right away, so the menu flashes open and shut. Keeping the handle is what
// makes the deferral safe: closing the menu cancels the pending open, so a
// timer started by one click can never reopen a menu that was closed since.
var lxcOpenTimer = null;

function lxcCancelOpen() {
  if (lxcOpenTimer !== null) {
    clearTimeout(lxcOpenTimer);
    lxcOpenTimer = null;
  }
}

// Rect of the element the click landed on: the icon on the container list, the
// whole button span on the dashboard. Not its .outer row: that block spans the
// name and the status columns, so when the pointer is on the left edge of a
// wide table cell the offset from the row's corner ends up hundreds of pixels
// wide and the menu lands far from the cursor.
function lxcAnchorRect(trigger) {
  if (!trigger || !trigger.getBoundingClientRect) return null;
  var r = trigger.getBoundingClientRect();
  return { left: r.left, bottom: r.bottom, top: r.top, width: r.width };
}

function lxcPositionMenu(menu, point) {
  if (!menu || !point) return;
  var margin = 8;
  var vw = window.innerWidth;
  var vh = window.innerHeight;

  // Measure with the menu already displayed, but before it can be seen.
  menu.style.visibility = "hidden";
  menu.style.left = "0px";
  menu.style.top = "-9999px";
  var mw = menu.offsetWidth;
  var mh = menu.offsetHeight;
  if (!mw) mw = menu.getBoundingClientRect().width;
  if (!mh) mh = menu.getBoundingClientRect().height;
  menu.style.visibility = "";

  // The menu grows down and to the right from the pointer. When that would
  // cross the right or bottom edge it moves left or up instead, so it never
  // needs the scroll container to clip it.
  var left = point.x;
  if (left + mw > vw - margin) left = vw - mw - margin;
  if (left < margin) left = margin;

  var top = point.y;
  if (top + mh > vh - margin) top = point.y - mh;
  if (top < margin) top = margin;

  menu.style.left = left + "px";
  menu.style.top = top + "px";
}

function lxcCloseMenus() {
  lxcCancelOpen();
  var menus = document.getElementsByClassName("lxc-dropdown");
  for (var i = 0; i < menus.length; i++) {
    menus[i].classList.remove("show_lxc");
  }
  lxcOpenMenu = null;
  lxcOpenTrigger = null;
  lxcOpenOffset = null;
}

function showDropdown(contName, ev) {
  var menu = document.getElementById("dropdown_" + contName);
  if (!menu) return;

  // Clicking the icon that owns an open menu closes it. Read before the
  // deferred open below replaces the state.
  var wasOpen = menu.classList.contains("show_lxc");

  // Resolve what the click landed on and where inside it the pointer was.
  // Both are read now: the deferred open runs after the browser has finished
  // the event, and the event object is gone by then.
  // The clicked element is the icon (container list) or the button span that
  // holds it (dashboard), so it works for both pages. A scripted call without
  // an event falls back to the menu's own wrapper.
  var trigger = (ev && ev.target && ev.target.closest)
    ? ev.target.closest(".dropdown_btn") || ev.target
    : null;
  if (!trigger || !trigger.classList || !trigger.classList.contains("dropdown_btn")) {
    var button = document.querySelector(".dropdown_btn[onclick*=\"showDropdown('" + contName + "')\"]");
    trigger = button || (menu.closest ? menu.closest(".lxc-dropdown-wrapper") : null);
  }

  // Where the menu opens. With a pointer it opens at the pointer, so it
  // follows the cursor no matter which part of the row was clicked. Without
  // one (keyboard activation, scripted calls) the icon's bottom-right corner
  // is the fallback. Only the point is cached: it is re-anchored to the
  // trigger on scroll and resize.
  var anchor = lxcAnchorRect(trigger);
  var offset = null;
  if (ev && typeof ev.clientX === "number" && typeof ev.clientY === "number") {
    offset = anchor
      ? { dx: ev.clientX - anchor.left, dy: ev.clientY - anchor.top }
      : null;
  } else if (anchor) {
    offset = { dx: anchor.width, dy: anchor.bottom - anchor.top };
  }

  lxcCloseMenus();
  if (wasOpen) return;

  // The open is deferred by one tick of 100ms so the WebGUI's document-level
  // dropdown handling, which runs while this click bubbles, has finished. The
  // handle is kept in lxcOpenTimer so lxcCloseMenus() can cancel a pending
  // open: otherwise the timer outlives the menu it was meant to open and puts
  // it back on screen after the user has already dismissed it.
  lxcOpenTimer = setTimeout(function() {
    lxcOpenTimer = null;

    var menu = document.getElementById("dropdown_" + contName);
    if (!menu) return;

    // Re-measure: the page may have scrolled during the delay.
    var rect = lxcAnchorRect(trigger);
    if (!rect) rect = { left: 8, top: 8, bottom: 8, width: 0 };
    if (!offset) offset = { dx: 0, dy: rect.bottom - rect.top };
    var point = { x: rect.left + offset.dx, y: rect.top + offset.dy };

    menu.classList.add("show_lxc");
    lxcPositionMenu(menu, point);
    lxcOpenMenu = menu;
    lxcOpenTrigger = trigger;
    lxcOpenOffset = offset;
  }, 100);
}

// Keep an open menu attached to where it was opened from while the page
// scrolls or resizes. The trigger and the click offset are cached rather than
// the coordinates: the row moves with the page, so re-measuring is the only
// way the menu can follow it. Once the trigger has been scrolled out of view
// the menu is dismissed instead of floating around detached from its row.
function lxcReposition() {
  if (!lxcOpenMenu || !lxcOpenMenu.classList.contains("show_lxc")) return;
  var rect = lxcAnchorRect(lxcOpenTrigger);
  if (!rect) {
    lxcCloseMenus();
    return;
  }
  if (rect.bottom < 0 || rect.top > window.innerHeight) {
    lxcCloseMenus();
    return;
  }
  // Only re-anchor when the cached offset still points inside the trigger;
  // otherwise (missing offset, or a retargeted trigger smaller than the
  // cached one) fall back to its bottom-left corner.
  var offset = lxcOpenOffset;
  if (!offset || offset.dx < 0 || offset.dx > rect.width ||
      offset.dy < 0 || offset.dy > rect.bottom - rect.top) {
    offset = { dx: 0, dy: rect.bottom - rect.top };
  }
  lxcPositionMenu(lxcOpenMenu, { x: rect.left + offset.dx, y: rect.top + offset.dy });
}

window.addEventListener("scroll", lxcReposition, true);
window.addEventListener("resize", lxcReposition);

// Function that creates a new container
function createContainer(name, description, distribution, release, startcont, autostart, mac) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: LXC_T("Create Container"),
    onClose: function () {
      location.href = '/LXC';
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': 'createCONT',
        'name': name,
        'description': description,
        'distribution': distribution,
        'release': release,
        'startcont': startcont,
        'autostart': autostart,
        'mac': mac
      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>" + LXC_T("Creating container, please wait until the DONE button is displayed!") + "</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("<p>" + LXC_T("Creating container, please wait until the DONE button is displayed!") + "</p>");
        statusInterval = setInterval(function () {
          dialogContent.append(".");
        }, 5000);
      },
      success: function (data) {
    if (data.indexOf("lxc-error") === -1) {
          dialogContent.append("<p>" + LXC_T("To connect to the console from the container, start the container and select Console from the context menu.") + "</p>");
          dialogContent.append("<p>" + LXC_T("If you want to connect to the container console from the Unraid terminal, start the container and type in:") + "</p>");
          dialogContent.append("<p>lxc-attach " + name + "</p>")
          dialogContent.append('<p>' + LXC_T("It is recommended to attach to the corresponding shell by typing in for example:") + '</p>');
          dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
    }
        let HTMLline = '<p class="centered">';
        if (startcont) {
            HTMLline += '<button type="button" onclick="startConsole(\'' + name + '\', true);">' + LXC_T("Open Terminal") + '</button>';
        }
        HTMLline += '<button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\';">' + LXC_T("Done") + '</button>';
        HTMLline += '</p>';
        dialogContent.append(HTMLline);
        clearInterval(statusInterval);
      }
    });
  });
}

// Function that creates a new container from the Template
function createContainerCAApp(name, description, repository, webui, icon, startcont, autostart, convertbdev, mac, supportlink, donatelink) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: LXC_T("Create Container from Template"),
    onClose: function () {
      location.href = '/LXC';
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': 'createTEMPLATE',
        'name': name,
        'description': description,
        'repository': repository,
        'webui': webui,
        'icon': icon,
        'startcont': startcont,
        'autostart': autostart,
        'convertbdev': convertbdev,
        'mac': mac,
        'supportlink': supportlink,
        'donatelink': donatelink

      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>" + LXC_TF("Creating container %s from repository: %s<br/>Please wait until the DONE button is displayed, this can take a few minutes depending on the container size and connection speed...", name, repository) + "</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append("<p>" + LXC_TF("Creating container %s from repository: %s<br/>Please wait until the DONE button is displayed, this can take a few minutes depending on the container size and connection speed...", name, repository) + "</p>");
        statusInterval = setInterval(function () {
          dialogContent.append(".");
        }, 5000);
      },
      success: function (data) {
    if (data.indexOf("lxc-error") === -1) {
          dialogContent.append("<p>" + LXC_T("Check out the README.md from the container if further steps are necessary to configure the container!") + "</p>");
    }
        let HTMLline = '<p class="centered">';
        if (startcont) {
            HTMLline += '<button type="button" onclick="startConsole(\'' + name + '\', true);">' + LXC_T("Open Terminal") + '</button>';
        }
        HTMLline += '<button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\';">' + LXC_T("Done") + '</button>';
        HTMLline += '</p>';
        dialogContent.append(HTMLline);
        clearInterval(statusInterval);
      }
    });
  });
}

// Function that copies a container
function createCopy(name, description, container, autostart, mac) {
  let statusInterval;

  Shadowbox.open({
    content: '<div id="dialogContent" class="logLine spacing"></div>',
    player: 'html',
    title: LXC_T("Copy Container"),
    onClose: function () {
      location.href = '/LXC';
    },
    height: Math.min(screen.availHeight, 800),
    width: Math.min(screen.availWidth, 1200)
  });
  waitForElement("#dialogContent", function () {
    let dialogContent = $("#dialogContent");
    $.ajax({
      type: "POST",
      url: '/plugins/lxc/include/ajax.php',
      data: {
        'lxc': '',
        'action': 'copyCONT',
        'name': name,
        'description': description,
        'container': container,
        'autostart': autostart,
        'mac': mac
      },
      xhr: function() {
        // get the native XmlHttpRequest object
        const xhr = $.ajaxSettings.xhr();
        // set the onprogress event handler
        xhr.onprogress = function() {
          // replace the '#output' element inner HTML with the received part of the response
          dialogContent.html("<p>" + LXC_T("Copying container, please wait until the DONE button is displayed!") + "</p><p>" + xhr.responseText + "</p>");
        }
        return xhr;
      },
      beforeSend: function () {
        dialogContent.append(LXC_T("Copying container, please wait until the DONE button is displayed!"));
        statusInterval = setInterval(function () {
          dialogContent.append("<p>......</p>");
        }, 5000);
      },
      success: function (data) {
        dialogContent.append("<p>" + LXC_T("To connect to the console from the container, start the container and select Console from the context menu.") + "</p>");
        dialogContent.append("<p>" + LXC_T("If you want to connect to the container console from the Unraid terminal, start the container and type in:") + "</p>");
        dialogContent.append("<p>lxc-attach " + name + "</p>")
        dialogContent.append('<p>' + LXC_T("It is recommended to attach to the corresponding shell by typing in for example:") + '</p>');
        dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
        dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">' + LXC_T("Done") + '</button></p>');
        clearInterval(statusInterval);
      }
    });
  });
}

function showSpinner() {
  // Show spinner
  document.querySelector('.spinner').style.display = 'block';
}

$(function() {
  // Disables all spaces in input fields
  $('.forbidSpace').on({
    keydown: function (e) {
      if (e.which === 32)
        return false;
    }
  });

  // Listener for deleting snapshots
  $(".deleteSNAP").on("click", function() {
    let id = this.id.split(" ")[0];
    let snapshot = this.id.split(" ")[1];

    showDialog(function (confirmed) {
      if (confirmed) {
        let postData = {
          'lxc'      : '',
          'action'   : 'deleteSNAP',
          'container': id,
          'snapshot' : snapshot
        };
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(){
          parent.window.location.reload();
        });
      }
    }, "<span style=\"color:red;font-weight:bold;\">" + LXC_T("ATTENTION") + "</span><br/>" + LXC_T("Do you really want to destroy this Snapshot? This is IRREVERSIBLE and will delete the snapshot and all data in it!"));
  });

  // Listener for restoring a container in place from one of its snapshots
  $(".restoreSNAP").on("click", function() {
    let id = this.id.split(" ")[0];
    let snapshot = this.id.split(" ")[1];

    showPrompt(function (typed) {
      if (typeof typed !== "string" || typed === "") {
        return;
      }
      if (typed !== id) {
        showDialog(function () {}, LXC_T("The container name does not match. Nothing was restored."), LXC_T("Restore Container"));
        return;
      }
      showStatus("restoreSNAP", id, LXC_T("Restoring Container from snapshot"), "", { snapshot: snapshot });
    }, LXC_T("ATTENTION") + ": " + LXC_T("Restore Container"),
      LXC_TF("Do you really want to restore the LXC Container %s from snapshot %s?", id, snapshot) + "\n" +
      LXC_T("The current container will be destroyed and replaced by the snapshot. All changes made after this snapshot will be lost!") + "\n\n" +
      LXC_TF("Type the container name %s to confirm:", id),
      id);
  });

  // Listener for deleting backups
  $(".deleteBACKUP").on("click", function() {
    let id = this.id.split(" ")[0];
    let backup = this.id.split(" ")[1];

    showDialog(function (confirmed) {
      if (confirmed) {
        let postData = {
          'lxc'      : '',
          'action'   : 'deleteBACKUP',
          'container': id,
          'backup'   : backup
        };
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(){
          parent.window.location.reload();
        });
      }
    }, "<span style=\"color:red;font-weight:bold;\">" + LXC_T("ATTENTION") + "</span><br/>" + LXC_T("Do you really want to delete this backup? This is IRREVERSIBLE and will delete the backup!"));
  });

  // Listener for restoring from snapshot form
  $(document).on("submit", "form#fromSnapshot", function (event) {
    event.preventDefault();
    let statusInterval;
    let name = this.contName.value;
    let description = this.contDesc.value;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;
    Shadowbox.open({
      content: '<div id="dialogContent" class="logLine spacing"></div>',
      player: 'html',
      title: LXC_T("Restoring Container from snapshot"),
      onClose: function () {
        location.href = '/LXC';
      },
      height: Math.min(screen.availHeight, 800),
      width: Math.min(screen.availWidth, 1200)
    });
    waitForElement("#dialogContent", function () {
      let dialogContent = $("#dialogContent");
      $.ajax({
        type: "POST",
        url: '/plugins/lxc/include/ajax.php',
        data: {
          'lxc': '',
          'action': 'fromSnapshot',
          'name': name,
          'description': description,
          'container': container,
          'snapshot': snapshot,
          'autostart': autostart,
          'mac': mac
        },
        xhr: function() {
          // get the native XmlHttpRequest object
          const xhr = $.ajaxSettings.xhr();
          // set the onprogress event handler
          xhr.onprogress = function() {
            // replace the '#output' element inner HTML with the received part of the response
            dialogContent.html("<p>" + LXC_T("Creating container, please wait until the DONE button is displayed!") + "</p><p>" + xhr.responseText + "</p>");
          }
          return xhr;
        },
        beforeSend: function () {
          dialogContent.append("<p>" + LXC_T("Creating container, please wait until the DONE button is displayed!") + "</p>");
          statusInterval = setInterval(function () {
            dialogContent.append(".");
          }, 5000);
        },
        success: function (data) {
          dialogContent.append("<p style=\"color:green;\">" + LXC_TF("Restoring container %s done!", name) + "</p>");
          dialogContent.append("<p>" + LXC_T("To connect to the console from the container, start the container and select Console from the context menu.") + "</p>");
          dialogContent.append("<p>" + LXC_T("If you want to connect to the container console from the Unraid terminal, start the container and type in:") + "</p>");
          dialogContent.append("<p>lxc-attach " + name + "</p>")
          dialogContent.append('<p>' + LXC_T("It is recommended to attach to the corresponding shell by typing in for example:") + '</p>');
          dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
          dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">' + LXC_T("Done") + '</button></p>');
          clearInterval(statusInterval);
        }
      });
    });
  });

  // Listener for restoring from backup form
  $(document).on("submit", "form#fromBackup", function (event) {
    event.preventDefault();
    let statusInterval;
    let name = this.contName.value;
    let description = this.contDesc.value;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;
    Shadowbox.open({
      content: '<div id="dialogContent" class="logLine spacing"></div>',
      player: 'html',
      title: LXC_T("Restoring Container from backup"),
      onClose: function () {
        location.href = '/LXC';
      },
      height: Math.min(screen.availHeight, 800),
      width: Math.min(screen.availWidth, 1200),
    });
    waitForElement("#dialogContent", function () {
      let dialogContent = $("#dialogContent");
      $.ajax({
        type: "POST",
        url: '/plugins/lxc/include/ajax.php',
        data: {
          'lxc': '',
          'action': 'fromBackup',
          'name': name,
          'description': description,
          'container': container,
          'backup': backup,
          'autostart': autostart,
          'mac': mac
        },
        xhr: function() {
          // get the native XmlHttpRequest object
          const xhr = $.ajaxSettings.xhr();
          // set the onprogress event handler
          xhr.onprogress = function() {
            // replace the '#output' element inner HTML with the received part of the response
            dialogContent.html("<p>" + LXC_T("Creating container, please wait until the DONE button is displayed!") + "</p><p>" + xhr.responseText + "</p>");
          }
          return xhr;
        },
        beforeSend: function () {
          dialogContent.append("<p>" + LXC_T("Creating container, please wait until the DONE button is displayed!") + "</p>");
          statusInterval = setInterval(function () {
            dialogContent.append(".");
          }, 5000);
        },
        success: function (data) {
          dialogContent.append("<p style=\"color:green;\">" + LXC_TF("Restoring container %s done!", name) + "</p>");
          dialogContent.append("<p>" + LXC_T("To connect to the console from the container, start the container and select Console from the context menu.") + "</p>");
          dialogContent.append("<p>" + LXC_T("If you want to connect to the container console from the Unraid terminal, start the container and type in:") + "</p>");
          dialogContent.append("<p>lxc-attach " + name + "</p>")
          dialogContent.append('<p>' + LXC_T("It is recommended to attach to the corresponding shell by typing in for example:") + '</p>');
          dialogContent.append("<p>lxc-attach " + name + " /bin/bash</p>");
          dialogContent.append("<p>" + LXC_T("Note:") + " " + LXC_T("You will only see as much backups as you have configured in the global backup settings (oldest backups will be deleted first).") + "</p>");
          dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="top.Shadowbox.close(); location.href = \'/LXC\'">' + LXC_T("Done") + '</button></p>');
          clearInterval(statusInterval);
        },
      });
    });
  });

  // Listener for add container form
  $(document).on('submit','form#addContainer',function(event){
    event.preventDefault();
    let regex = /^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/;
    if (!regex.test(this.contMac.value)) {
      this.contMac.classList.add("err");
      $("#emac").css('visibility', 'visible')
      return false;
    }
    let distribution = this.contDistribution.value;
    let release = this.contRelease.value;
    let name = this.contName.value;
    let description = this.contDesc.value;
    let startcont = this.contStart.checked;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;

    createContainer(name, description, distribution, release, startcont, autostart, mac);
  });

  // Listener for copying container
  $(document).on('submit','form#copyCont',function(event){
    event.preventDefault();
    let regex = /^([0-9A-F]{2}[:-]){5}([0-9A-F]{2})$/;
    if (!regex.test(this.contMac.value)) {
      this.contMac.classList.add("err");
      $("#emac").css('visibility', 'visible')
      return false;
    }
    let name = this.contName.value;
    let description = this.contDesc.value;
    let container = this.contSnap.value;
    let autostart = this.contAutostart.checked;
    let mac = this.contMac.value;
    createCopy(name, description, container, autostart, mac);
  });

  // Listener for all button actions
  $(".stopCONT, .restartCONT, .freezeCONT, .killCONT, .unfreezeCONT, .startCONT, .startALLCONT, .stopALLCONT, .freezeALLCONT, .unfreezeALLCONT").on("click", function(e) {
    e.stopImmediatePropagation();
    showSpinner();
    postAction($(this).attr("class"), this.id);
  });


  // Listener to edit container config
  document.addEventListener('click', function(e) {
    if (e.target.id === "editconfig") {
      e.stopImmediatePropagation();
      let container = $(e.target).attr("class");

      Shadowbox.open({
        content: '<div id="dialogContent" class="logLine spacing"></div>',
        player: 'html',
        title: LXC_T("Show/Edit Configuration"),
        onClose: function() {
          location.reload();
        },
        height: Math.min(screen.availHeight, 750),
        width: Math.min(screen.availWidth, 900)
      });

      waitForElement("#dialogContent", function() {
        $.ajax({
          type: "POST",
          url: '/plugins/lxc/include/ajax.php',
          data: {
            'lxc': '',
            'action': "showConfig",
            'container': container
          },
          success: function(data) {
            let dialogContent = $("#dialogContent");
            dialogContent.append('<textarea id="configEditor" style="width: 100%; max-width: 800px; height: 600px; margin: 0 auto; display: block; padding: 10px; font-family: monospace; box-sizing: border-box;">' + data + '</textarea>');
            dialogContent.append('<p class="centered" style="color: red;">' + LXC_T("WARNING: Saving the configuration will restart a running container!") + '</p>');
            dialogContent.append('<p class="centered"><button class="logLine" type="button" onclick="saveConfig(\'' + container + '\')">' + LXC_T("Save") + '</button><button class="logLine" type="button" onclick="top.Shadowbox.close();">' + LXC_T("Done") + '</button></p>');
          }
        });
      });
    }
  });

  // Choosing an item closes the menu. This runs in the capture phase on
  // purpose: the item handlers below call stopImmediatePropagation(), so a
  // bubble-phase listener would never see the click and the menu would stay
  // on screen after the action fired. Clicks on the icon are left to
  // showDropdown(), which toggles the menu itself.
  document.addEventListener('click', function(e) {
    if (e.target && e.target.closest && e.target.closest(".lxc-dropdown")) {
      lxcCloseMenus();
    }
  }, true);

  let checkboxes = $("input[type=checkbox].lxc")
  checkboxes.change(function(e) {
    e.stopImmediatePropagation();
    let postData = {
      'lxc'   : '',
      'action'     : "autostart",
      'container': this.id,
      'autostart': $(this).is(':checked')
    };
    $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){});
  });

  $(document).mouseup(function (e) {
    if ($(e.target).closest(".btn_dropdown, .dropdown_btn, .dropdown-menu, .lxc-dropdown").length === 0) {
      lxcCloseMenus();
    }
  });

  // Listener to destroy container
  $(".destroyCONT").on("click", function() {
    let id = this.id
    showDialog(function(response) {
      if (response) {
        showStatus("destroyCONT", id, LXC_T("Destroy Container"), LXC_TF("Destroying Container %s", id));
      }
    }, "<span style=\"color:red;font-weight:bold;\">" + LXC_T("ATTENTION") + "</span><br/>" + LXC_TF("Do you really want to destroy the LXC Container: <span style=\"font-weight:bold\">%s</span>?", id) + "<br/>" + LXC_T("This is IRREVERSIBLE and will delete the container, snapshots and all data in it!") + "<br/><br/><i>" + LXC_T("Backups will not be deleted!") + "</i>");
  });

  // Listener to snapshot container
  $(".snapshotCONT").on("click", function() {
    let id = this.id;
    showDialog(function(response) {
      if (response) {
        showStatus("snapshotCONT", id, LXC_T("Snapshot Container"), LXC_TF("Snapshotting Container %s", id));
      }
    }, LXC_T("This action will stop the LXC Container and start it again if it was running."));
  });

  // Listener to backup container
  $(".backupCONT").on("click", function() {
    let id = this.id;
    showDialog(function(response) {
      if (response) {
        showStatus("backupCONT", id, LXC_T("Backup Container"), LXC_TF("Backup Container %s", id));
      }
    }, LXC_T("This action will stop the LXC Container and start it again if it was running.") + "<br/><br/><span style=\"font-weight:bold;\">" + LXC_T("Note:") + "</span> <i>" + LXC_T("You will only see as much backups as you have configured in the global backup settings (oldest backups will be deleted first).") + "</i>");
  });

  // Listener to set description
  $(".descCONT").on("click", function() {
    let id = this.id;
    showPrompt(function(response) {
      if (response === "") {
        let postData = {
          'lxc'   : '',
          'action'     : "delDescription",
          'container': id
        };    
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      } else if (response != undefined && response != null && response != false && response != "" && response.length <= 50 && /^[\w.]+/.test( response )) {
        let postData = {
          'lxc'   : '',
          'action'     : "setDescription",
          'container': id,
          'description': response
        };
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      }
    }, LXC_T('Description'), LXC_T("Max. 50 alphanumeric characters.\nLeave empty to delete the description."), LXC_T('Description or empty'))
  });

  $(".webuiCONT").on("click", function() {
    let id = this.id;
    showPrompt(function(response) {
      if (response === "") {
        let postData = {
          'lxc'   : '',
          'action'     : "delWebUIURL",
          'container': id
        };    
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      } else if (response != undefined && response != null && response != false && response != "" && /^(https?|ftp):\/\/[^\s/$.?#].[^\s]*$/i.test( response )) {
        let postData = {
          'lxc'   : '',
          'action'     : "setWebUIURL",
          'container': id,
          'webuiurl': response
        };
        $.post("/plugins/lxc/include/ajax.php", postData).done(function(response){
          parent.window.location.reload();
        });
      }
    }, LXC_T('WebUI URL'), LXC_T("Enter your URL like: http://192.168.0.10:8080 or https://subdomain.yourdomain.net\nLeave empty to delete the WebUI URL."), LXC_T('WebUI URL or empty'))
  });

  // Listener for add container from CA App
  $(document).on('submit','form#addContainerCAApp',function(event){
    event.preventDefault();
    let name = this.contName.value;
    let description = this.contDesc.value;
    let repository = this.contRepo.value;
    let webui = this.contWebUI.value;
    let icon = this.contIcon.value;
    let startcont = this.contStart.checked;
    let autostart = this.contAutostart.checked;
    let convertbdev = this.contConvBDEV.checked;
    let mac = this.contMac.value;
    let supportlink = this.supportLink.value;
    let donatelink = this.donateLink.value;

    createContainerCAApp(name, description, repository, webui, icon, startcont, autostart, convertbdev, mac, supportlink, donatelink);
  });

})

$(function () {
  // The script can be injected again on navigation: switching a second time
  // would append another label pair and break the layout.
  $('.autostart').each(function () {
    if ($(this).data('lxcSwitchButton')) {
      return;
    }
    $(this).data('lxcSwitchButton', true).switchButton({labels_placement:'right', on_label:LXC_T("On"), off_label:LXC_T("Off")});
  });
});
