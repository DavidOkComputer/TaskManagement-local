$(function() {
  /* ChartJS
   * -------
   * Chart initialization without hardcoded data
   * Data will be loaded dynamically from dashboard_charts.js
   */
  'use strict';
  
  // Initialize empty charts - data will be populated dynamically
  // by dashboard_charts.js
  
  console.log('Chart.js initialized - waiting for dynamic data from dashboard_charts.js');
  
  // The charts below will be initialized with empty data
  // and replaced with actual data when loadDepartmentView() or loadComparisonView() are called
  
  // Bar Chart
  if ($("#barChart").length) {
    console.log('Bar chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Line Chart
  if ($("#lineChart").length) {
    console.log('Line chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Multi-line Chart
  if ($("#linechart-multi").length) {
    console.log('Multi-line chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Multi-area Chart
  if ($("#areachart-multi").length) {
    console.log('Multi-area chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Doughnut Chart
  if ($("#doughnutChart").length) {
    console.log('Doughnut chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Pie Chart
  if ($("#pieChart").length) {
    console.log('Pie chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Area Chart
  if ($("#areaChart").length) {
    console.log('Area chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Scatter Chart
  if ($("#scatterChart").length) {
    console.log('Scatter chart container found');
    // Will be initialized by dashboard_charts.js
  }

  // Browser Traffic Chart
  if ($("#browserTrafficChart").length) {
    console.log('Browser traffic chart container found');
    // Will be initialized by dashboard_charts.js
  }
});