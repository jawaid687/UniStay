document.addEventListener("DOMContentLoaded", function () {
    const themeToggle = document.getElementById("themeToggle");

    const savedTheme = localStorage.getItem("theme");

    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
        if (themeToggle) {
            themeToggle.innerHTML = "☀️ Light Mode";
        }
    } else {
        document.body.classList.remove("dark-mode");
        if (themeToggle) {
            themeToggle.innerHTML = "🌙 Dark Mode";
        }
    }

    if (themeToggle) {
        themeToggle.addEventListener("click", function () {
            document.body.classList.toggle("dark-mode");

            if (document.body.classList.contains("dark-mode")) {
                localStorage.setItem("theme", "dark");
                themeToggle.innerHTML = "☀️ Light Mode";
            } else {
                localStorage.setItem("theme", "light");
                themeToggle.innerHTML = "🌙 Dark Mode";
            }
        });
    }
});