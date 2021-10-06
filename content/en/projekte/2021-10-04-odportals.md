---
layout: project
lab: OK Lab Karlsruhe #needed for Aggregation on Lab-Page
imgname: karlsruhe/od-portals.png
title: Open Data Portals
showcase: 1
status: Active, contributors welcome

collaborators:
  - name: Michael Riedmüller

links:
  - name: Preliminary documentation and data
    url: https://cloud.ok-lab-karlsruhe.de/index.php/s/ANrwjtZCD3qMGEE

---

CKAN is a widely used system for open data portals. In addition to the web application, it offers automated retrieval via a programming interface (CKAN API). Available data can be searched and retrieved via this API. However, cross-portal queries are not provided for in the CKAN API.

In order to implement this, an approach was chosen in the project with a freely available tool that prepares the data queried via the API and makes it available to a business intelligence tool (BI tool) (as CSV files). The BI tool used here was Power BI Desktop, which is also freely available (under Windows). The evaluated results are also available as CSV files and can be further used with other tools. The corresponding links will follow shortly.

The following tasks are (prototypically) processed:

  * Retrieve content (catalog and resources) from CKAN portals.
  * Prepare the content and make it available to other tools in a machine-processable form
  * Create data models for the available objects
  * Create sample evaluations on the prepared data
  * Identify problems with retrieval and/or preparation
  * Derive quality criteria for the provided content and its form
  * Identify possibilities for improvement
  * Establish comparability between portals and content


