$(function() {
  /* ChartJS cargar informacion automaticamente desde dashboard_charts.js*/
  'use strict';
  console.log('Chart.js initialized - waiting for dynamic data from dashboard_charts.js');
  
  // Bar Chart
  if ($("#barChart").length) {
    console.log('Bar chart container found');}

  // Line Chart
  if ($("#lineChart").length) {
    console.log('Line chart container found');}

  // Multi-line Chart
  if ($("#linechart-multi").length) {
    console.log('Multi-line chart container found');}

  // Multi-area Chart
  if ($("#areachart-multi").length) {
    console.log('Multi-area chart container found');}

  // Doughnut Chart
  if ($("#doughnutChart").length) {
    console.log('Doughnut chart container found');}

  // Pie Chart
  if ($("#pieChart").length) {
    console.log('Pie chart container found');}

  // Area Chart
  if ($("#areaChart").length) {
    console.log('Area chart container found');}

  // Scatter Chart
  if ($("#scatterChart").length) {
    console.log('Scatter chart container found');}

  // Browser Traffic Chart
  if ($("#browserTrafficChart").length) {
    console.log('Browser traffic chart container found');}
});