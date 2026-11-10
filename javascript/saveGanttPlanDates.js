// 20261110 CDX/NTR Share planning payloads and failed-save recovery across Gantt charts.

/**
 * Build a drag/resize callback that saves dates and reloads persisted dates on failure.
 *
 * @param {string} endpoint Planning AJAX endpoint.
 * @param {string} failureMessage Localized message shown before reloading.
 * @returns {function(Object): void} Gantt drag/resize callback.
 */
function createGanttPlanDateSaver(endpoint, failureMessage) {
    return function (data) {
        $.ajax({
            type: "POST",
            url: endpoint,
            data: {
                planfra: data.start.toString("dd-MM-yyyy"),
                plantil: data.end.toString("dd-MM-yyyy"),
                id: data.id == null ? 0 : data.id,
                opg_id: data.opg_id == null ? 0 : data.opg_id
            },
            error: function () {
                window.alert(failureMessage);
                window.location.reload();
            }
        });
    };
}
