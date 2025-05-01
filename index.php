<?php
session_start();
$page_title = "Welcome to Pharmacy Management System";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Floating Pills Background (expanded with more pills) */
        body {
            background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        .pill {
            position: absolute;
            border-radius: 50px;
            opacity: 0.7;
            animation: float 15s infinite ease-in-out;
        }
        .pill-1 {
            width: 40px;
            height: 20px;
            background: #ffffff;
            top: 10%;
            left: 15%;
        }
        .pill-2 {
            width: 60px;
            height: 30px;
            background: #ffeb3b;
            top: 60%;
            left: 25%;
            animation-delay: 2s;
        }
        .pill-3 {
            width: 50px;
            height: 25px;
            background: #42a5f5;
            top: 30%;
            left: 70%;
            animation-delay: 4s;
        }
        .pill-4 {
            width: 30px;
            height: 15px;
            background: #ffffff;
            top: 80%;
            left: 80%;
            animation-delay: 6s;
        }
        .pill-5 {
            width: 45px;
            height: 22px;
            background: #34d399; /* Green pill */
            top: 20%;
            left: 90%;
            animation-delay: 3s;
        }
        .pill-6 {
            width: 35px;
            height: 17px;
            background: #f87171; /* Red pill */
            top: 70%;
            left: 10%;
            animation-delay: 5s;
        }
        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0); }
        }
        .navbar {
            background-color: #1e3a8a; /* bg-blue-900 */
        }
        .navbar a {
            color: #ffffff; /* text-white */
            position: relative;
            transition: all 0.3s ease;
        }
        .navbar a:hover {
            background-color: #1e40af; /* hover:bg-blue-700 */
        }
        .navbar a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: #ffffff;
            transition: width 0.3s ease;
        }
        .navbar a:hover::after {
            width: 100%;
        }
        .illustration {
            transition: transform 0.3s ease;
        }
        .illustration:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="font-sans">
    <!-- Floating Pills -->
    <div class="pill pill-1"></div>
    <div class="pill pill-2"></div>
    <div class="pill pill-3"></div>
    <div class="pill pill-4"></div>
    <div class="pill pill-5"></div>
    <div class="pill pill-6"></div>

    <!-- Navigation Bar -->
    <nav class="navbar p-4 shadow-md">
        <div class="container mx-auto flex justify-end space-x-4">
            <a href="login.php" class="px-4 py-2 rounded-md text-sm font-medium">
                <i class="fas fa-sign-in-alt mr-2"></i>Login
            </a>
            <a href="register.php" class="px-4 py-2 rounded-md text-sm font-medium">
                <i class="fas fa-user-plus mr-2"></i>Register
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto p-6 flex flex-col md:flex-row items-center justify-between min-h-[calc(100vh-80px)] bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg shadow-lg">
        <!-- Text Section with Icons -->
        <div class="md:w-1/2 mb-6 md:mb-0 p-6 bg-white rounded-lg shadow-md">
            <div class="flex items-center mb-4">
                <i class="fas fa-mortar-pestle text-3xl text-blue-900 mr-3"></i>
                <h1 class="text-4xl font-bold text-blue-900">Best Pharmacy Management System</h1>
            </div>
            <div class="flex items-center">
                <i class="fas fa-prescription text-2xl text-blue-900 mr-3"></i>
                <p class="text-lg text-blue-900"> Welcome to MediPeak!  Discover a vibrant, seamless pharmacy experience where your prescriptions are handled with speed, precision, and care. Track orders, connect with our team, and elevate your wellness journey. At MediPeak, your health reaches new heights! This will help you to manage budgeting, billing, and reporting of pharmaceutical sector</p>
            </div>
        </div>

        <!-- Illustration -->
        <div class="md:w-1/2 flex justify-center">
            <img src="assets/images/medicine.svg" alt="Pharmacy Illustration" class="illustration max-w-full h-auto">
        </div>
    </div>
</body>
</html>