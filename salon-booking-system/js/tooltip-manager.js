/**
 * Modern Tooltip Manager for Salon Booking Calendar
 * Replaces title attribute approach with proper templates and accessibility
 */

class TooltipManager {
  constructor() {
    this.activeTooltip = null;
    this.tooltipElement = null;
    this.activeTrigger = null;
    this.init();
  }

  init() {
    this.createTooltipContainer();
    this.bindEvents();
  }

  createTooltipContainer() {
    // Create tooltip container if it doesn't exist
    if (!document.getElementById("sln-tooltip-container")) {
      const container = document.createElement("div");
      container.id = "sln-tooltip-container";
      container.className = "sln-tooltip-container";
      container.setAttribute("role", "tooltip");
      container.setAttribute("aria-hidden", "true");
      document.body.appendChild(container);
      this.tooltipElement = container;
    } else {
      this.tooltipElement = document.getElementById("sln-tooltip-container");
    }
  }

  bindEvents() {
    // Handle tooltip triggers for our modern tooltip system
    document.addEventListener("click", (e) => {
      const trigger = e.target.closest("[data-sln-tooltip]");
      if (trigger) {
        // Prevent retriggering if tooltip is already open for this element
        if (this.activeTooltip && this.activeTrigger === trigger) {
          e.preventDefault();
          return;
        }
        
        // Additional safeguard: check data attribute
        if (trigger.getAttribute("data-tooltip-active") === "true") {
          e.preventDefault();
          return;
        }
        
        e.preventDefault();
        this.showTooltip(trigger);
      }
    });

    // Handle tooltip dismiss
    document.addEventListener("click", (e) => {
      if (
        e.target.closest(".sln-tooltip-dismiss") ||
        e.target.closest(".sln-tooltip-close")
      ) {
        this.hideTooltip();
      }
    });

    // Keyboard navigation
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && this.activeTooltip) {
        this.hideTooltip();
      }
    });

    // Hide tooltip when clicking outside
    document.addEventListener("click", (e) => {
      if (
        this.activeTooltip &&
        !e.target.closest(".sln-tooltip-container") &&
        !e.target.closest("[data-sln-tooltip]")
      ) {
        this.hideTooltip();
      }
    });
  }

  showTooltip(trigger) {
    const tooltipId = trigger.getAttribute("data-tooltip-id");
    const tooltipType = trigger.getAttribute("data-tooltip-type") || "booking";

    // Hide existing tooltip
    this.hideTooltip();

    // Store reference to the trigger element
    this.activeTrigger = trigger;

    // Get tooltip data
    const tooltipData = this.getTooltipData(trigger, tooltipType);

    // Generate tooltip content
    const content = this.generateTooltipContent(tooltipData, tooltipType);

    // Show tooltip
    this.tooltipElement.innerHTML = content;
    this.tooltipElement.setAttribute("aria-hidden", "false");
    this.tooltipElement.style.display = "block";
    this.tooltipElement.style.visibility = "visible";
    this.activeTooltip = tooltipId;

    // Add class and data attribute to the trigger element
    trigger.classList.add("sln-booking-tooltip-active");
    trigger.setAttribute("data-tooltip-active", "true");

    // Position tooltip
    this.positionTooltip(trigger);

    // Initialize PRO feature dialogs
    this.initProFeatureDialogs();

    // Disable scrolling on the calendar panel when tooltip is open
    this.disableScrolling();

    // Add show class for animations
    setTimeout(() => {
      this.tooltipElement.classList.add("sln-tooltip--visible");
    }, 10);
  }

  hideTooltip() {
    if (this.tooltipElement) {
      this.tooltipElement.classList.remove("sln-tooltip--visible");
      this.tooltipElement.setAttribute("aria-hidden", "true");

      // Remove class and data attribute from the trigger element
      if (this.activeTrigger) {
        this.activeTrigger.classList.remove("sln-booking-tooltip-active");
        this.activeTrigger.removeAttribute("data-tooltip-active");
        this.activeTrigger = null;
      }

      this.activeTooltip = null;

      // Re-enable scrolling when tooltip is hidden
      this.enableScrolling();
    }
  }

  getTooltipData(trigger, type) {
    if (type === "booking") {
      return {
        id: trigger.getAttribute("data-event-id"),
        title:
          trigger.getAttribute("data-modern-tooltip-title") ||
          trigger.getAttribute("data-event-title") ||
          trigger.getAttribute("data-label-booking-details") ||
          "Booking Details",
        amount: trigger.getAttribute("data-event-amount") || "",
        discount: trigger.getAttribute("data-event-discount") || "",
        deposit: trigger.getAttribute("data-event-deposit") || "",
        due: trigger.getAttribute("data-event-due") || "",
        tax: trigger.getAttribute("data-event-tax") || "",
        transactionFee:
          trigger.getAttribute("data-event-transaction-fee") || "",
        duration: trigger.getAttribute("data-event-duration") || "",
        status: trigger.getAttribute("data-event-status") || "",
        statusColor: trigger.getAttribute("data-event-status-color") || "#1b1b21",
        tips: trigger.getAttribute("data-event-tips") || "",
        services: trigger.getAttribute("data-event-services") || "",
        attendants: trigger.getAttribute("data-event-attendants") || "",
        customerId: trigger.getAttribute("data-customer-id") || "",
        customerPhone: trigger.getAttribute("data-customer-phone") || "",
        customerEmail: trigger.getAttribute("data-customer-email") || "",
        bookingChannel: trigger.getAttribute("data-booking-channel") || "",
        bookingNote: trigger.getAttribute("data-booking-note") || "",
        bookingShop: trigger.getAttribute("data-booking-shop") || "",
        isPro: trigger.getAttribute("data-is-pro") === "true",
        noShow: trigger.getAttribute("data-no-show") === "true",
        deleteUrl: trigger.getAttribute("data-delete-url") || "",
        // Translated labels
        labels: {
          totalAmount:
            trigger.getAttribute("data-label-total-amount") || "Total amount",
          bookingId:
            trigger.getAttribute("data-label-booking-id") || "Booking ID",
          phone: trigger.getAttribute("data-label-phone") || "Phone",
          email: trigger.getAttribute("data-label-email") || "Email",
          channel: trigger.getAttribute("data-label-channel") || "Channel",
          customerNote:
            trigger.getAttribute("data-label-customer-note") || "Customer Note",
          shop: trigger.getAttribute("data-label-shop") || "Shop",
          discount: trigger.getAttribute("data-label-discount") || "Discount",
          deposit: trigger.getAttribute("data-label-deposit") || "Deposit",
          due: trigger.getAttribute("data-label-due") || "Due",
          tax: trigger.getAttribute("data-label-tax") || "Tax",
          transactionFee:
            trigger.getAttribute("data-label-transaction-fee") ||
            "Transaction fee",
          // Action button labels
          edit: trigger.getAttribute("data-label-edit") || "Edit",
          noShow: trigger.getAttribute("data-label-no-show") || "No show",
          customer: trigger.getAttribute("data-label-customer") || "Customer",
          delete: trigger.getAttribute("data-label-delete") || "Delete",
          duplicate:
            trigger.getAttribute("data-label-duplicate") || "Duplicate",
          duplicatePro:
            trigger.getAttribute("data-label-duplicate-pro") ||
            "Duplicate (Pro)",
          // Additional tooltip labels
          duration: trigger.getAttribute("data-label-duration") || "Duration",
          status: trigger.getAttribute("data-label-status") || "Status",
          services: trigger.getAttribute("data-label-services") || "Services",
          attendants:
            trigger.getAttribute("data-label-attendants") || "Attendants",
          tips: trigger.getAttribute("data-label-tips") || "Tips",
          close: trigger.getAttribute("data-label-close") || "Close",
          confirmDelete:
            trigger.getAttribute("data-label-confirm-delete") ||
            "Are you sure?",
          yesDelete:
            trigger.getAttribute("data-label-yes-delete") || "Yes, delete",
          cancel: trigger.getAttribute("data-label-cancel") || "Cancel",
          bookingDetails:
            trigger.getAttribute("data-label-booking-details") ||
            "Booking Details",
          unlockFeature:
            trigger.getAttribute("data-label-unlock-feature") ||
            "unlock this feature for",
          year: trigger.getAttribute("data-label-year") || "year",
        },
      };
    }
    return {};
  }

  generateTooltipContent(data, type) {
    if (type === "booking") {
      return this.generateBookingTooltip(data);
    }
    return (
      '<div class="sln-tooltip-content">' +
      (data.labels?.bookingDetails || "Tooltip content") +
      "</div>"
    );
  }

  generateBookingTooltip(data) {
    const isPro = data.isPro;

    return `
            <div class="sln-tooltip-content sln-booking-tooltip">
                <div class="sln-tooltip-header">
                    <h4 class="sln-tooltip-title">${data.title}</h4>
                    <button class="sln-tooltip-close sln-btn--new sln-icon--new sln-icon--new--x" aria-label="${
                      data.labels.close
                    }">
                    </button>
                </div>
                
                <div class="sln-tooltip-body">
                    <div class="sln-tooltip-details--top">
                        ${
                          data.bookingShop
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--shop">
                            <span class="sln-tooltip-label">${data.labels.shop}</span>
                            <span class="sln-tooltip-value">${data.bookingShop}</span>
                        </div>
                        `
                            : ""
                        }
                        <div class="sln-tooltip-detail sln-tooltip-detail--id-status">
                            <span class="sln-tooltip-value">
                                <span class="sln-tooltip-label sln-tooltip-label--icon">
                                    <i class="sln-icon sln-icon--id-badge" aria-hidden="true"></i>
                                </span>
                                ${data.id}
                            </span>
                            ${
                              data.status
                                ? `<span class="sln-tooltip-status-badge">
                                    <span class="sln-tooltip-status-indicator" style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: ${data.statusColor}; margin-right: 4px; vertical-align: middle;"></span>
                                    <span class="sln-tooltip-status-label" style="color: ${data.statusColor}; font-size: 12px; font-weight: 500; vertical-align: middle;">${data.status}</span>
                                </span>`
                                : ""
                            }
                        </div>
                        ${
                          data.bookingChannel
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--channel">
                            <span class="sln-tooltip-label sln-tooltip-label--icon">
                                <i class="sln-icon sln-icon--arrow-left-from-bracket" aria-hidden="true"></i>
                            </span>
                            <span class="sln-tooltip-value">${this.formatChannelWithUsername(data.bookingChannel)}</span>
                        </div>
                        `
                            : ""
                        }
                        ${
                          data.customerPhone
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--phone">
                            <span class="sln-tooltip-label sln-tooltip-label--icon">
                                <i class="sln-icon sln-icon--phone" aria-hidden="true"></i>
                            </span>
                            <span class="sln-tooltip-value">${data.customerPhone}</span>
                            <a href="https://wa.me/${data.customerPhone.replace(/[^0-9]/g, '')}" target="_blank" rel="noopener noreferrer" class="sln-tooltip-whatsapp-link" title="Open WhatsApp chat">
                                <i class="sln-icon sln-icon--whatsapp" aria-hidden="true"></i>
                            </a>
                        </div>
                        `
                            : ""
                        }
                        ${
                          data.customerEmail
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--email">
                            <span class="sln-tooltip-label sln-tooltip-label--icon">
                                <i class="sln-icon sln-icon--envelope" aria-hidden="true"></i>
                            </span>
                            <span class="sln-tooltip-value">${data.customerEmail}</span>
                        </div>
                        `
                            : ""
                        }
                        ${
                          data.bookingNote
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--customer-note">
                            <span class="sln-tooltip-label sr-only">${data.labels.customerNote}</span>
                            <span class="sln-tooltip-value">${data.bookingNote}</span>
                        </div>
                        `
                            : ""
                        }
                    </div>
                    <div class="sln-tooltip-details--bottom">
                        <div class="sln-tooltip-detail sln-tooltip-detail--total-amount">
                            <span class="sln-tooltip-label">${
                              data.labels.totalAmount
                            }</span>
                            <span class="sln-tooltip-value">${
                              data.amount
                            }</span>
                        </div>
                        ${
                          data.duration
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--duration">
                            <span class="sln-tooltip-label">${data.labels.duration}</span>
                            <span class="sln-tooltip-value">${data.duration}</span>
                        </div>
                        `
                            : ""
                        }
                        ${
                          data.services
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--services">
                            <span class="sln-tooltip-label">${data.labels.services}</span>
                            <span class="sln-tooltip-value">${data.services}</span>
                        </div>
                        `
                            : ""
                        }
                        ${
                          data.attendants
                            ? `
                        <div class="sln-tooltip-detail sln-tooltip-detail--attendants">
                            <span class="sln-tooltip-label">${data.labels.attendants}</span>
                            <span class="sln-tooltip-value">${data.attendants}</span>
                        </div>
                        `
                            : ""
                        }
                        ${
                          isPro
                            ? `
                            ${
                              data.discount
                                ? `
                            <div class="sln-tooltip-detail sln-tooltip-detail--discount">
                                <span class="sln-tooltip-label">${data.labels.discount}</span>
                                <span class="sln-tooltip-value">${data.discount}</span>
                            </div>
                            `
                                : ""
                            }
                            ${
                              data.deposit
                                ? `
                            <div class="sln-tooltip-detail sln-tooltip-detail--deposit">
                                <span class="sln-tooltip-label">${data.labels.deposit}</span>
                                <span class="sln-tooltip-value">${data.deposit}</span>
                            </div>
                            `
                                : ""
                            }
                            ${
                              data.tax
                                ? `
                            <div class="sln-tooltip-detail sln-tooltip-detail--tax">
                                <span class="sln-tooltip-label">${data.labels.tax}</span>
                                <span class="sln-tooltip-value">${data.tax}</span>
                            </div>
                            `
                                : ""
                            }
                            ${
                              data.transactionFee
                                ? `
                            <div class="sln-tooltip-detail sln-tooltip-detail--transaction-fee">
                                <span class="sln-tooltip-label">${data.labels.transactionFee}</span>
                                <span class="sln-tooltip-value">${data.transactionFee}</span>
                            </div>
                            `
                                : ""
                            }
                            ${
                              data.tips
                                ? `
                            <div class="sln-tooltip-detail sln-tooltip-detail--tips">
                                <span class="sln-tooltip-label">${data.labels.tips}</span>
                                <span class="sln-tooltip-value">${data.tips}</span>
                            </div>
                            `
                                : ""
                            }
                            ${
                              data.due
                                ? `
                            <div class="sln-tooltip-detail sln-tooltip-detail--due">
                                <span class="sln-tooltip-label">${data.labels.due}</span>
                                <span class="sln-tooltip-value">${data.due}</span>
                            </div>
                            `
                                : ""
                            }
                        `
                            : ""
                        }
                    </div>
                    
                    <div class="sln-tooltip-actions">
                        <a class="sln-tooltip-action sln-edit-icon-tooltip" 
                           href="" 
                           data-bookingid="${data.id}" 
                           aria-label="${data.labels.edit}">
                            <i class="sln-icon sln-icon--ellipsis"></i>
                        </a>
                        
                        ${
                          isPro
                            ? `
                            <div class="sln-tooltip-action-wrapper">
                                <a class="sln-tooltip-action sln-no-show-icon-tooltip ${
                                  data.noShow ? "active" : ""
                                }" 
                                   data-bookingid="${data.id}" 
                                   data-no-show="${data.noShow ? 1 : 0}"
                                   data-booking-selector="booking-id-${data.id}"
                                   aria-label="${data.labels.noShow}">
                                    <i class="sln-icon sln-icon--no-show"></i>
                                </a>
                            </div>
                        `
                            : `
                            <div class="sln-tooltip-action-wrapper sln-tooltip-action-wrapper--disabled sln-tooltip-action-wrapper--pro-feature">
                                <a class="sln-tooltip-action sln-no-show-icon-tooltip-free sln-tooltip-action--disabled ${
                                  data.noShow ? "active" : ""
                                }" 
                                   data-bookingid="${data.id}" 
                                   aria-label="${data.labels.noShow}">
                                    <i class="sln-icon sln-icon--no-show"></i>
                                </a>
                                <div class="sln-profeature sln-profeature--disabled sln-profeature__tooltip-wrapper">
                                    <div class="sln-profeature__cta sln-profeature--button--bare sln-profeature--modal-footer__actions">
                                        <a href="#nogo" class="sln-profeature__open-button" data-tiptext="Switch to PRO to unlock this feature! Click to know more.">
                                            <span class="sr-only">Switch to PRO to unlock this feature!</span>
                                        </a>
                                        <dialog class="sln-profeature__dialog">
                                            <h3 class="sln-profeature__tooltip__title">Unlock this feature today for a special price.</h3>
                                            <h4 class="sln-profeature__tooltip__bullet">Get access to all PRO features</h4>
                                            <h4 class="sln-profeature__tooltip__bullet">Get access to Mobile Web App</h4>
                                            <h4 class="sln-profeature__tooltip__bullet">Activate online payments</h4>
                                            <h4 class="sln-profeature__tooltip__bullet">Get email priority support</h4>
                                            <h4 class="sln-profeature__tooltip__bullet">Download our add-ons for free</h4>
                                            <div class="sln-profeature__tooltip__cta">
                                                <a href="https://www.salonbookingsystem.com/homepage/plugin-pricing/?utm_source=default_status&utm_medium=free-edition-back-end&utm_campaign=unlock_feature&utm_id=GOPRO" target="_blank">
                                                    Switch to <strong>PR<span>O</span></strong>
                                                </a>
                                                <h6 class="sln-profeature__tooltip__btn-info">Get 15% discount</h6>
                                            </div>
                                            <a href="#nogo" class="sln-profeature__close-button">
                                                <span class="sr-only">Close dialog</span>
                                            </a>
                                            <div class="sln-profeature__dialog-fakedrop"></div>
                                        </dialog>
                                    </div>
                                </div>
                            </div>
                        `
                        }
                        
                        <a class="sln-tooltip-action sln-tooltip-customer" 
                           href="admin.php?page=salon-customers&id=${
                             data.customerId
                           }" 
                           target="_blank"
                           aria-label="${data.labels.customer}">
                            <i class="sln-icon sln-icon--user"></i>
                        </a>
                        
                        <div class="sln-tooltip-action sln-trash-icon-tooltip" 
                             data-bookingid="${data.id}" 
                             aria-label="${data.labels.delete}">
                            <i class="sln-icon sln-icon--delete"></i>
                        </div>
                    </div>
                </div>


                
                <div class="sln-confirm-delete-tooltip" style="display: none;">
                    <div class="sln-confirm-delete-tooltip__content">
                        <div class="sln-dtn__title">${data.labels.confirmDelete}</div>
                        <a class="sln-dtn-danger-tooltip sln-btn--new sln-btn--main25 sln-btn--medium" href="${
                          data.deleteUrl
                        }" data-bookingid="${data.id}">
                            ${data.labels.yesDelete}
                        </a>
                        <a class="sln-dtn-close-tooltip sln-btn--new sln-btn--borderonly25 sln-btn--medium">
                            ${data.labels.cancel}
                        </a>
                    </div>
                </div>
            </div>
        `;
  }

  formatChannelWithUsername(channel) {
    // Format channel with username in smaller font
    // Example: "Back-end (Dimitri)" -> "Back-end <small>(Dimitri)</small>"
    const usernameMatch = channel.match(/^(.+?)\s+\((.+?)\)$/);
    if (usernameMatch) {
      const origin = usernameMatch[1];
      const username = usernameMatch[2];
      return `${origin} <span style="font-size: 0.85em; color: #637491;">(${username})</span>`;
    }
    return channel;
  }

  positionTooltip(trigger) {
    const rect = trigger.getBoundingClientRect();
    const tooltip = this.tooltipElement;

    // Calculate position
    const top = rect.top + window.scrollY - 10;
    const left = rect.right + 10;

    // Apply position
    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
    tooltip.style.position = "absolute";
    tooltip.style.zIndex = "9999";

    // Check if tooltip goes off screen and adjust
    const tooltipRect = tooltip.getBoundingClientRect();
    const viewportWidth = window.innerWidth;

    if (tooltipRect.right > viewportWidth) {
      tooltip.style.left = `${rect.left - tooltipRect.width - 10}px`;
    }
  }

  disableScrolling() {
    // Find the calendar panel wrapper
    const calendarPanel = document.querySelector(".cal-day-panel__wrapper");
    if (calendarPanel) {
      // Store original overflow style
      this.originalOverflow = calendarPanel.style.overflow;
      // Disable scrolling
      calendarPanel.style.overflow = "hidden";
      // Add class to indicate tooltip is in view
      calendarPanel.classList.add("cal-day-tooltip-inview");
    }
  }

  enableScrolling() {
    // Restore original overflow style
    const calendarPanel = document.querySelector(".cal-day-panel__wrapper");
    if (calendarPanel && this.originalOverflow !== undefined) {
      calendarPanel.style.overflow = this.originalOverflow;
      // Remove class when tooltip is dismissed
      calendarPanel.classList.remove("cal-day-tooltip-inview");
    }
  }

  initProFeatureDialogs() {
    // Initialize PRO feature dialog handlers within the tooltip
    const proFeatureElements = this.tooltipElement.querySelectorAll(
      ".sln-tooltip-action-wrapper--pro-feature",
    );

    proFeatureElements.forEach((wrapper) => {
      const dialog = wrapper.querySelector(".sln-profeature__dialog");
      const openButton = wrapper.querySelector(".sln-profeature__open-button");
      const closeButton = wrapper.querySelector(
        ".sln-profeature__close-button",
      );

      if (!dialog || !openButton || !closeButton) {
        return;
      }

      // Open modal on click
      openButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        dialog.showModal();
        dialog.classList.add("open");
      });

      // Close modal on close button click
      closeButton.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        dialog.close();
        dialog.classList.remove("open");
      });

      // Close modal on backdrop click
      dialog.addEventListener("click", (event) => {
        if (event.target.nodeName === "DIALOG") {
          dialog.close();
          dialog.classList.remove("open");
        }
      });
    });
  }
}

// Initialize when DOM is ready
document.addEventListener("DOMContentLoaded", () => {
  window.slnTooltipManager = new TooltipManager();
});

// Also initialize immediately if DOM is already ready
if (document.readyState === "loading") {
  // DOM is still loading, wait for DOMContentLoaded
} else {
  // DOM is already ready, initialize immediately
  window.slnTooltipManager = new TooltipManager();
}

// Export for use in other scripts
if (typeof module !== "undefined" && module.exports) {
  module.exports = TooltipManager;
}
